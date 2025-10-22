<?php
namespace App\Models;

use App\Models\Enums\UserRelationType;
use App\Services\AssessmentService;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use Notifiable;
    
    protected $table = 'user';
    public $timestamps = false;
    
    /**
     * SECURITY FIX: Removed $guarded = [] and implemented strict $fillable whitelist
     * 
     * Fields intentionally EXCLUDED from mass assignment for security:
     * - type: Prevents privilege escalation (user cannot set themselves as admin/CEO)
     * - removed_at: Prevents restoring deleted users
     * - email_verified_at: Must be controlled by verification process
     * - remember_token: Managed by framework
     * - has_auto_level_up: Business logic controlled
     * - created_at, updated_at: Automatic timestamps
     */
    protected $fillable = [
        'email',
        'name',
        'password',  // Note: setPasswordAttribute handles hashing
        'locale',
    ];
    
    protected $hidden = [
        'password',
        'remember_token',
    ];
    
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
    
    protected $appends = ['has_password', 'login_mode_text'];

    public function setPasswordAttribute($value)
    {
        if (empty($value)) {
            $this->attributes['password'] = null;
            return;
        }

        $isAlreadyHashed =
            Str::startsWith($value, '$2y$') ||        // bcrypt
            Str::startsWith($value, '$argon2i$') ||
            Str::startsWith($value, '$argon2id$');    // argon2

        $this->attributes['password'] = $isAlreadyHashed ? $value : Hash::make($value);
    }

    public function getHasPasswordAttribute(): bool
    {
        return !is_null($this->password);
    }

    public function getLoginModeTextAttribute(): string
    {
        return $this->has_password ? 'jelszó + OAuth' : 'OAuth';
    }

    public function logins(){
        return $this->hasMany(UserLogin::class, 'user_id', 'id')->orderByDesc('logged_in_at');
    }

    public function organizations(){
        return $this->belongsToMany(\App\Models\Organization::class, 'organization_user', 'user_id', 'organization_id')
            ->withPivot('role');
    }

    public function getLastLogin(){
        return $this->logins()->first();
    }

    public function getNameOfType(){
        return __("usertypes.$this->type"); 
    }

    public function allRelations(){
        return $this->hasMany(UserRelation::class, 'user_id', 'id');
    }
    
    public function relations(){
        return $this->allRelations()
            ->where('organization_id', session('org_id'))
            ->where('type', '!=', UserRelationType::SELF);
    }

    public function competencies()
    {
        return $this->belongsToMany(Competency::class, 'user_competency', 'user_id', 'competency_id')
                    ->withPivot('organization_id');
    }
    
    public function competencySubmits($assessmentId = 0){
        if($assessmentId == 0){
            if(!is_null(($assessment = AssessmentService::getCurrentAssessment()))){
                $assessmentId = $assessment->id;
            }
        }
        return $this->hasMany(UserCompetencySubmit::class,'user_id', 'id')->where('assessment_id', $assessmentId)->where('target_id', '!=', $this->id);
    }
        
    public function selfCompetencySubmit($assessmentId = 0){
        if($assessmentId == 0){
            if(!is_null(($assessment = AssessmentService::getCurrentAssessment()))){
                $assessmentId = $assessment->id;
            }
        }
        return $this->hasOne(UserCompetencySubmit::class,'user_id', 'id')->where('assessment_id', $assessmentId)->where('target_id', $this->id);
    }

    public function ceoRanks($assessmentId = 0){
        if($assessmentId == 0){
            if(!is_null(($assessment = AssessmentService::getCurrentAssessment()))){
                $assessmentId = $assessment->id;
            }
        }
        return $this->hasMany(UserCeoRank::class,'user_id', 'id')->where('assessment_id', $assessmentId);
    }

    public function competencyQuestions(){
        $orgId = session('org_id');
        return $this->hasManyThrough(
            CompetencyQuestion::class,
            UserCompetency::class,
            'user_id',
            'competency_id',
            'id',
            'competency_id'
        )
        ->whereNull('competency_question.removed_at')
        ->whereHas('competency', function($q) use ($orgId) {
            $q->whereNull('organization_id')->orWhere('organization_id', $orgId);
        });
    }

    public function assessedCompetencies($assessmentId, $type){
        if($assessmentId == 0){
            if(!is_null(($assessment = AssessmentService::getCurrentAssessment()))){
                $assessmentId = $assessment->id;
            }
        }
        return $this->hasMany(CompetencySubmit::class, 'target_id', 'id')->where('assessment_id', $assessmentId)->where('type', $type);
    }

    public function bonusMalus(){
        return $this->hasMany(UserBonusMalus::class,'user_id', 'id')->orderByDesc('month');
    }
    
    public function getBonusMalusInMonth($month){
        $last = $this->bonusMalus()->first();
        return $this->bonusMalus()->where('month', $month)->first() ?? new UserBonusMalus([
            "user_id" => $this->id,
            "level" => $last ? $last->level : 0,
            "month" => $month
        ]);
    }

    public function getRoleInOrg(int $orgId): ?string
{
    return \App\Services\RoleHelper::getUserRole($this->id, $orgId);
}

/**
 * Get user's role in current session organization
 */
public function getCurrentRole(): ?string
{
    return \App\Services\RoleHelper::getCurrentRole($this->id);
}

/**
 * Check if user has a specific role in organization
 */
public function hasRoleInOrg(int $orgId, string $role, bool $strict = false): bool
{
    return \App\Services\RoleHelper::hasRole($this->id, $orgId, $role, $strict);
}

/**
 * Check if user is admin in organization
 */
public function isAdminInOrg(int $orgId): bool
{
    return $this->getRoleInOrg($orgId) === \App\Models\Enums\OrgRole::ADMIN;
}

/**
 * Check if user is CEO in organization
 */
public function isCeoInOrg(int $orgId): bool
{
    return $this->getRoleInOrg($orgId) === \App\Models\Enums\OrgRole::CEO;
}

/**
 * Check if user is Manager in organization
 */
public function isManagerInOrg(int $orgId): bool
{
    return $this->getRoleInOrg($orgId) === \App\Models\Enums\OrgRole::MANAGER;
}

/**
 * Check if user is admin in current session organization
 */
public function isCurrentAdmin(): bool
{
    $orgId = session('org_id');
    return $orgId ? $this->isAdminInOrg($orgId) : false;
}

/**
 * Check if user is CEO in current session organization
 */
public function isCurrentCeo(): bool
{
    $orgId = session('org_id');
    return $orgId ? $this->isCeoInOrg($orgId) : false;
}

/**
 * Check if user is Manager in current session organization
 */
public function isCurrentManager(): bool
{
    $orgId = session('org_id');
    return $orgId ? $this->isManagerInOrg($orgId) : false;
}

/**
 * Get all roles this user has across all organizations
 */
public function getAllRoles(): array
{
    return \App\Services\RoleHelper::getAllUserRoles($this->id);
}

/**
 * Check if user can manage another user in organization
 */
public function canManage(User $targetUser, int $orgId): bool
{
    return \App\Services\RoleHelper::canManage($this->id, $targetUser->id, $orgId);
}
}