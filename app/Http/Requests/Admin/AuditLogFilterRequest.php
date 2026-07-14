<?php

namespace App\Http\Requests\Admin;

use App\Models\SecurityAuditLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditLogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', SecurityAuditLog::class) === true;
    }

    public function rules(): array
    {
        return [
            'event' => ['nullable', 'string', 'max:120'],
            'severity' => ['nullable', Rule::in(['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'])],
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'ip' => ['nullable', 'ip'],
            'route' => ['nullable', 'string', 'max:255'],
            'method' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'integer', 'min:100', 'max:599'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
