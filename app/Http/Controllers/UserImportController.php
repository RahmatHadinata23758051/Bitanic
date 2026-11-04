<?php

namespace App\Http\Controllers;

use App\Actions\User\BulkImportUsersAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserImportController extends Controller
{
    public function preview(Request $request, BulkImportUsersAction $action): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
        ]);

        try {
            $result = $action->preview($validated['file'], $request->user()->school_id);
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['file' => 'Berkas tidak dapat dibaca. Pastikan CSV atau XLSX memiliki baris header dan format yang valid.']);
        }

        return response()->json($result);
    }

    public function execute(Request $request, BulkImportUsersAction $action): JsonResponse
    {
        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:500'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.email' => ['required', 'email', 'max:255'],
            'rows.*.role' => ['required', 'in:siswa,guru'],
            'rows.*.identifier_number' => ['nullable', 'string', 'max:100'],
            'rows.*.class_grade' => ['nullable', 'string', 'max:100'],
            'rows.*.group_name' => ['nullable', 'string', 'max:100'],
            'rows.*.assigned_kit' => ['nullable', 'string', 'max:100'],
        ]);

        $result = $action->execute($validated['rows'], $request->user()->school_id, $request->user());
        return response()->json($result);
    }
}
