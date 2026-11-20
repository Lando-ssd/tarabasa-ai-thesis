<?php

namespace App\Services;

use RuntimeException;

/** The payment provider could not be reached, or refused a request. The message never holds a key and is safe to log. */
class PaymentProviderException extends RuntimeException {}
