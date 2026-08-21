<?php

namespace App\Enums\Ai;

enum AiRunStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case InvalidOutput = 'invalid_output';
    case Cached = 'cached';
}
