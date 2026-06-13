<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ErrorCatalogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $needle = Str::upper(trim((string) $request->query('code')));
        $errors = collect(config('security_errors'))
            ->flatMap(fn (array $group): array => array_values($group))
            ->filter(fn (array $error): bool => $needle === '' || Str::contains(Str::upper($error['code']), $needle))
            ->sortBy('code');

        return view('admin.error-catalog.index', [
            'errors' => $errors,
            'code' => $needle,
        ]);
    }
}
