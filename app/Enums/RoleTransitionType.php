<?php

namespace App\Enums;

/** user_role_transitions.transition_type — APPROVED DECISION Q2. */
enum RoleTransitionType: string
{
    case Elevation = 'elevation';     // Employee → Team Leader (temporary period starts)
    case Restoration = 'restoration'; // back to the preserved base role
}
