<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case NotRequired = 'Not Required';   // free document, or a request created before online payment
    case Submitted = 'Submitted';        // student entered details, AI has not confirmed yet
    case Paid = 'Paid';                  // AI confirmed the payment details and proof
    case Flagged = 'Flagged';            // AI found a problem; student may resubmit
}
