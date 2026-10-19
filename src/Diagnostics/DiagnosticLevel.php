<?php

declare(strict_types=1);

namespace Gcego\Registry\Diagnostics;

enum DiagnosticLevel: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Error = 'error';
}
