<?php

namespace App\Http\Controllers;

use App\Models\CeoRank;
use App\Services\AjaxService;
use App\Services\AssessmentService;
use Illuminate\Http\Request;

class AdminCeoRanksController extends Controller
{
    public function __construct(){
        if(AssessmentService::isAssessmentRunning()){
            return abort(403);
        }
    }

    public function index(Request $request){
        return view('admin.ceoranks',[
            "ceoranks" => CeoRank::whereNull('removed_at')->orderByDesc('value')->get(),
        ]);
    }

    public function getCeoRank(Request $request){
        return CeoRank::findOrFail($request->id);
    }

    public function saveCeoRank(Request $request){
        $rank = CeoRank::find($request->id);

        $rules = [
            "name" => ['required'],
            "value" => ['required', 'numeric', 'min:0', 'max:100'],
            "min" => ['required', 'numeric', 'min:0', 'max:100'],
            "max" => ['required', 'numeric', 'min:0', 'max:100'],
        ];

        $attributes = [
            "name" => __('admin/ceoranks.name'),
            "value" => __('admin/ceoranks.value'),
            "min" => __('admin/ceoranks.min'),
            "max" => __('admin/ceoranks.max'),
        ];
    
        $this->validate(
            request: $request,
            rules: $rules,
            customAttributes: $attributes,
        ); 

        AjaxService::DBTransaction(function() use ($request, &$rank){
            if(is_null($rank)){
                CeoRank::create([
                    "name" => $request->name,
                    "value" => $request->value,
                    "min" => $request->min == 0 ? null : $request->min,
                    "max" => $request->max == 0 ? null : $request->max,
                ]);
            }else{
                $rank->name = $request->name;
                $rank->value = $request->value;
                $rank->min = $request->min == 0 ? null : $request->min;
                $rank->max = $request->max == 0 ? null : $request->max;
                $rank->save();
            }
        });
    }

    public function removeCeoRank(Request $request){
        $rank = CeoRank::findOrFail($request->id);
        AjaxService::DBTransaction(function() use(&$rank) {
            $rank->removed_at = date('Y-m-d H:i:s');
            $rank->save();
        });
    }

}

