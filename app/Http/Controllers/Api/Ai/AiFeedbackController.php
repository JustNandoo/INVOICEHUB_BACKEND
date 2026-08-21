<?php

namespace App\Http\Controllers\Api\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ai\StoreAiFeedbackRequest;
use App\Models\AiFeedback;
use App\Models\AiRun;
use Illuminate\Http\JsonResponse;

class AiFeedbackController extends Controller
{
    public function store(StoreAiFeedbackRequest $request, int $aiRun): JsonResponse
    {
        $run = AiRun::query()->where('user_id', $request->user()->id)->findOrFail($aiRun);
        $data = $request->validated();

        $feedback = AiFeedback::query()->updateOrCreate(
            ['ai_run_id' => $run->id, 'user_id' => $request->user()->id],
            [
                'accepted' => $data['accepted'] ?? null,
                'rating' => $data['rating'] ?? null,
                'corrected_value' => $data['correctedValue'] ?? null,
                'comment' => $data['comment'] ?? null,
            ],
        );

        return response()->json([
            'success' => true,
            'message' => 'Terima kasih, masukan Anda tersimpan.',
            'data' => ['feedback' => [
                'id' => $feedback->id,
                'aiRunId' => $feedback->ai_run_id,
                'accepted' => $feedback->accepted,
                'rating' => $feedback->rating,
                'comment' => $feedback->comment,
            ]],
        ], 201);
    }
}
