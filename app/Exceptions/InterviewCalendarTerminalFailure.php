<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class InterviewCalendarTerminalFailure extends RuntimeException implements ShouldntReport {}
