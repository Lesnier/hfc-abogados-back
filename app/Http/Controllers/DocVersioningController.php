<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\DocVersion;
use App\Models\DocFile;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DocVersioningController extends Controller
{
    // Create a new version (cloning previous files)
    public function createVersion(Request $request, $employeeId)
    {
        $employee = Employee::findOrFail($employeeId);
        
        // Find latest version
        $latestVersion = $employee->docVersions()->orderBy('version_number', 'desc')->first();
        $nextVersionNumber = $latestVersion ? $latestVersion->version_number + 1 : 1;

        // Create new version
        $newVersion = DocVersion::create([
            'employee_id' => $employee->id,
            'version_number' => $nextVersionNumber,
            'effective_date' => Carbon::now()
        ]);

        $docColumns = [
            'form_931', 'policy', 'life_insurance', 'salary_receipt', 
            'repetition', 'indemnity', 'proof_discharge', 'arca_termination_form'
        ];

        // Clone files from latest version if exists AND has files
        if ($latestVersion && $latestVersion->files()->count() > 0) {
            foreach ($latestVersion->files as $file) {
                DocFile::create([
                    'doc_version_id' => $newVersion->id,
                    'doc_type' => $file->doc_type,
                    'file_path' => $file->file_path, // Points to same file initially
                    'is_approved' => $file->is_approved
                ]);
            }
        } else {
            // First Version: Import from Employee table (Legacy Data)
            foreach ($docColumns as $col) {
                if (!empty($employee->$col) && $employee->$col != '[]') {
                    // Voyager sometimes stores '[]' for empty multiple files, or raw path
                    DocFile::create([
                        'doc_version_id' => $newVersion->id,
                        'doc_type' => $col,
                        'file_path' => $employee->$col,
                        'is_approved' => false // Default to not approved for imported legacy
                    ]);
                }
            }
        }

        return response()->json(['success' => true, 'message' => 'Nueva versión creada', 'version' => $newVersion]);
    }

    // Upload or Update a file for a specific version
    public function uploadFile(Request $request, $versionId)
    {
        $request->validate([
            'file' => 'required|file',
            'doc_type' => 'required|string'
        ]);

        $version = DocVersion::findOrFail($versionId);
        $file = $request->file('file');
        
        // Store file
        $path = $file->store('employees/documents', 'public');

        // Formato Voyager JSON
        $fileData = json_encode([[
            'download_link' => $path,
            'original_name' => $file->getClientOriginalName()
        ]]);

        // Update or Create DocFile record
        $docFile = DocFile::updateOrCreate(
            ['doc_version_id' => $version->id, 'doc_type' => $request->doc_type],
            ['file_path' => $fileData, 'is_approved' => false] // Store JSON
        );

        // SYNC with Main Employee Table (Reflect logic for legacy compatibility)
        // Check if this version is the LATEST one effectively
        $latestVersion = DocVersion::where('employee_id', $version->employee_id)
                                   ->orderBy('version_number', 'desc')
                                   ->first();

        if ($latestVersion->id == $version->id) {
            $employee = $version->employee;
            // Map doc_type to employee column if applicable
            $columnName = $request->doc_type;
            
            $employee->$columnName = $fileData; // Store JSON in employee table too
            $employee->save();
        }

        return response()->json(['success' => true, 'path' => Storage::url($path), 'docFile' => $docFile]);
    }

    // Toggle Approval status
    public function toggleApproval(Request $request, $fileId)
    {
        $docFile = DocFile::findOrFail($fileId);
        $docFile->is_approved = !$docFile->is_approved;
        $docFile->save();

        return response()->json(['success' => true, 'new_status' => $docFile->is_approved]);
    }
    // Save Note for a file
    public function saveNote(Request $request, $fileId)
    {
        $request->validate(['note' => 'nullable|string']);
        
        $docFile = DocFile::findOrFail($fileId);
        $docFile->note = $request->note;
        $docFile->save();

        return response()->json(['success' => true]);
    }
}
