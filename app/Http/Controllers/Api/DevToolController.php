<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class DevToolController extends Controller
{
    private $filePath = 'dev/testing_checklist.json';

    /**
     * Get the testing checklist data.
     */
    public function getTestingChecklist()
    {
        if (!Storage::exists($this->filePath)) {
            return response()->json([]);
        }

        $content = Storage::get($this->filePath);
        return response()->json(json_decode($content, true) ?: []);
    }

    /**
     * Save the testing checklist data.
     */
    public function saveTestingChecklist(Request $request)
    {
        $data = $request->all();

        // Ensure directory exists
        if (!Storage::exists('dev')) {
            Storage::makeDirectory('dev');
        }

        Storage::put($this->filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return response()->json(['message' => 'Checklist saved successfully']);
    }
}
