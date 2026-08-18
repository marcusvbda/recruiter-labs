<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class InterviewCalendarOperationUnavailable extends RuntimeException implements ShouldntReport {}
