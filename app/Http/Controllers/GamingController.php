<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use App\Models\Otp;
use App\Models\Score;
use App\Models\UserRegister;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GamingController extends Controller
{
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_no' => 'nullable|numeric|digits_between:9,12',
        ]);
        if ($validator->fails()) {
            return response(['errors' => $validator->errors()], 422);
        }
        $phoneNo = $request->phone_no;

        $otp = '1234'; // static OTP
        $expiresAt = Carbon::now()->addMinute();

        // for given phone no. otp exists delete that otp
        Otp::where('phone_no', $phoneNo)->delete();

        // Store new OTP 
        Otp::create([
            'phone_no' => $phoneNo,
            'otp' => $otp,
            'expires_at' => $expiresAt,
        ]);
        return response()->json(['status' => 'success', 'message' => 'OTP sent successfully'], 200);
    }

    public function register(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'phone_no' => 'required|digits:10|unique:user_register,phone_no',
            'name' => 'required|string|max:255',
            'dob' => 'required|date',
            // 'email' => 'required|email|unique:user_register,email',
            'email' => 'required|email',
            'otp' => 'required|digits:4',
        ]);
        if ($validator->fails()) {
            return response(['errors' => $validator->errors()], 422);
        }

        // Check OTP
        $otpData = Otp::where('phone_no', $request->phone_no)
            ->where('otp', $request->otp)
            ->orderByDesc('created_at')
            ->first();
        //return $otpData;
        if (!$otpData) {
            return response()->json(['status' => 'error', 'message' => 'Invalid OTP'], 401);
        }

        if (Carbon::now()->gt($otpData->expires_at)) {
            return response()->json(['status' => 'error', 'message' => 'OTP expired'], 401);
        }

        // Create user
        $user = UserRegister::create([
            'phone_no' => $request->phone_no,
            'name' => $request->name,
            'dob' => $request->dob,
            'email' => $request->email,
        ]);

        // Generate JWT token
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'status' => 'success',
            'message' => 'Registration successful',
            'token' => $token
        ]);
    }

    public function saveScore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'score' => 'required|integer|between:50,500',
        ]);
        if ($validator->fails()) {
            return response(['errors' => $validator->errors()], 422);
        }
        $user = Auth::user();

        // Count today’s scores for this user
        $scoreCountToday = Score::where('user_id', $user->id)
            ->whereDate('created_at', Carbon::today())
            ->count();

        if ($scoreCountToday >= 3) {
            return response()->json([
                'status' => 'error',
                'message' => 'You can only submit score 3 times per day not more than 3 times'
            ], 403);
        }

        Score::create([
            'user_id' => $user->id,
            'score' => $request->score,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Score saved successfully.'
        ]);
    }

    public function overallScore()
    {
        $user = auth()->user();

        // 1. Calculate total score of each user
        $userScores = Score::select('user_id', DB::raw('SUM(score) as total_score'))
            ->groupBy('user_id')
            ->orderByDesc('total_score')
            ->get();

        // 2. Find total score and rank of the current user
        $rank = 0;
        $userTotal = 0;

        foreach ($userScores as $index => $score) {
            if ($score->user_id === $user->id) {
                $userTotal = $score->total_score;
                $rank = $index + 1;
                break;
            }
        }

        return response()->json([
            'status' => 'success',
            'total_score' => $userTotal,
            'rank' => $rank > 0 ? $rank : null,
        ]);
    }

    public function weeklyScore()
    {
        $user = auth()->user();

        // Week 1 starts on 28 March 2025 (Friday)
        $startOfWeek1 = Carbon::create(2025, 3, 28)->startOfDay();
        $now = Carbon::now();

        $weeks = [];
        $weekNo = 1;
        $startOfWeek = $startOfWeek1->copy();

        while ($startOfWeek->lte($now)) {
            $endOfWeek = $startOfWeek->copy()->addDays(6)->endOfDay(); // Thursday

            // Get total scores per user in this week
            $weeklyScores = DB::table('scores')
                ->select('user_id', DB::raw('SUM(score) as total'))
                ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                ->groupBy('user_id')
                ->orderByDesc('total')
                ->get();

            // Determine current user's rank and total
            $rank = 0;
            $userScore = 0;

            foreach ($weeklyScores as $index => $row) {
                if ($row->user_id == $user->id) {
                    $userScore = $row->total;
                    $rank = $index + 1;
                    break;
                }
            }

            $weeks[] = [
                'weekNo' => $weekNo,
                'rank' => $rank,
                'totalScore' => (int) $userScore,
            ];

            $weekNo++;
            $startOfWeek->addWeek(); // Move to next week (Friday)
        }

        return response()->json([
            'success' => true,
            'weeks' => $weeks,
        ]);
    }
}
