<?php

namespace App\Models\Enums;


class UserType
{
    const SUPERADMIN = "superadmin";
    const GUEST = "guest";
    const NORMAL = "normal";
    
    /** @deprecated Use OrgRole::ADMIN instead */
    const ADMIN = "admin";
    
    /** @deprecated Use OrgRole::MANAGER instead */
    const MANAGER = "manager";
    
    /** @deprecated Use OrgRole::CEO instead */
    const CEO = "ceo";
}