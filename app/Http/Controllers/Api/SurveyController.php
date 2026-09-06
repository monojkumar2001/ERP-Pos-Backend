<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Survey;
use Carbon\Carbon;
use App\Models\Response;
use App\Models\ResponseDetail;
use App\Models\Option;
use App\Models\Question;
class SurveyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $today = Carbon::now()->timezone('Asia/Dhaka')->startOfDay();
        $surveys = Survey::with(['questions.options'])
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Active surveys retrieved successfully.',
            'data' => $surveys,
        ]);
    }

    public function show($slug)
    {
        $today = Carbon::now()->timezone('Asia/Dhaka')->startOfDay();
        $survey = Survey::with(['questions.options'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first();

        if (!$survey) {
            return response()->json(
                [
                    'status' => false,
                    'message' => 'Survey not found or not available.',
                ],
                404,
            );
        }

        return response()->json([
            'status' => true,
            'message' => 'Survey loaded successfully.',
            'data' => $survey,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'survey_id' => 'required|exists:surveys,id',
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,id',
            'answers.*.option_ids' => 'required|array|min:1',
            'answers.*.option_ids.*' => 'required|exists:options,id',

            // Demographics validation
            'age' => 'required|string',
            'gender' => 'required|string|in:male,female,other',
            'occupation' => 'required|string',
            'marital_status' => 'required|string|in:single,married,divorced,widowed',
        ]);

        $survey = Survey::findOrFail($validated['survey_id']);

        $response = Response::create([
            'survey_id' => $survey->id,
            'user_identifier' => $request->header('X-USER-ID') ?? null,
            'ip_address' => $request->ip(),
            'browser' => $request->header('User-Agent'),
            'os' => php_uname('s'),
            'isp' => $request->header('X-ISP') ?? null,
            'country' => $request->header('X-COUNTRY') ?? null,
            'city' => $request->header('X-CITY') ?? null,

            // Demographic fields
            'age' => $validated['age'],
            'gender' => $validated['gender'],
            'occupation' => $validated['occupation'],
            'marital_status' => $validated['marital_status'],
        ]);

        foreach ($validated['answers'] as $answer) {
            foreach ($answer['option_ids'] as $optionId) {
                ResponseDetail::create([
                    'response_id' => $response->id,
                    'question_id' => $answer['question_id'],
                    'option_id' => $optionId,
                ]);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Survey response submitted successfully!',
        ]);
    }
}
