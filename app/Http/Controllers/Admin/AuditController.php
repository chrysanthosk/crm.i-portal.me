<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $q = AuditLog::query()->with('user')->orderByDesc('id');

        if ($request->filled('category')) {
            $q->where('category', $request->string('category'));
        }
        if ($request->filled('action')) {
            $q->where('action', 'like', '%' . $request->string('action') . '%');
        }
        if ($request->filled('user_id')) {
            $q->where('user_id', (int)$request->input('user_id'));
        }
        if ($request->filled('ip')) {
            $q->where('ip', 'like', '%' . $request->string('ip') . '%');
        }
        if ($request->filled('from')) {
            $q->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $q->whereDate('created_at', '<=', $request->input('to'));
        }
        if ($request->filled('search')) {
            $s = '%' . $request->string('search') . '%';
            $q->where(function ($qq) use ($s) {
                $qq->where('action', 'like', $s)
                   ->orWhere('category', 'like', $s)
                   ->orWhere('target_type', 'like', $s)
                   ->orWhere('target_id', 'like', $s)
                   ->orWhere('ip', 'like', $s);
            });
        }

        $logs = $q->paginate(25)->withQueryString();

        $categories = AuditLog::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();

        $users = User::query()->select('id','email','first_name','last_name')->orderBy('email')->get();

        return view('admin.audit.index', compact('logs', 'categories', 'users'));
    }
}
