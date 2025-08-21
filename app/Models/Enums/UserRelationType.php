<?php

namespace App\Models\Enums;

class UserRelationType
{
    const EQUAL = "colleague";
    const UNDER = "subordinate";
    const ABOVE = "superior";
    const SELF = "self";
}
