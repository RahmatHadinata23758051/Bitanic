<?php

namespace App\Http\Controllers\Admin;

use App\Actions\User\BulkImportUsersAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserImportController extends Controller
{
    public function preview(Request $request, BulkImportUsersAction $action): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->isSuperuser() && ! $user->school_id) {
            abort(403, 'Akses impor pengguna memerlukan asosiasi sekolah yang valid.');
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
        ]);

        $targetSchoolId = ($user && ! $user->isSuperuser())
            ? $user->school_id
            : ($request->input('school_id') ? (int) $request->input('school_id') : $user?->school_id);

        try {
            $result = $action->preview($validated['file'], $targetSchoolId);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['file' => 'Berkas tidak dapat dibaca. Pastikan CSV atau XLSX memiliki baris header dan format yang valid.']);
        }

        return response()->json($result);
    }

    public function execute(Request $request, BulkImportUsersAction $action): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->isSuperuser() && ! $user->school_id) {
            abort(403, 'Akses impor pengguna memerlukan asosiasi sekolah yang valid.');
        }

        $validated = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'rows' => ['required', 'array', 'min:1', 'max:500'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.email' => ['required', 'email', 'max:255'],
            'rows.*.role' => ['required', 'in:siswa,guru'],
            'rows.*.identifier_number' => ['nullable', 'string', 'max:100'],
            'rows.*.class_grade' => ['nullable', 'string', 'max:100'],
            'rows.*.group_name' => ['nullable', 'string', 'max:100'],
            'rows.*.assigned_kit' => ['nullable', 'string', 'max:100'],
        ]);

        $targetSchoolId = ($user && ! $user->isSuperuser())
            ? $user->school_id
            : ($request->input('school_id') ? (int) $request->input('school_id') : $user?->school_id);

        $result = $action->execute($validated['rows'], $targetSchoolId, $user);

        return response()->json($result);
    }
}
