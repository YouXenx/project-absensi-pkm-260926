<?php

namespace App\Http\Middleware;

use App\Models\AcademicYear;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAcademicYear
{
    /**
     * Steps after "Buat Tahun Ajaran" need an active year. The resolved year is shared
     * as the "activeAcademicYear" request attribute for the controller.
     *
     * "active-year:promoted" additionally requires step 2 (kenaikan kelas) to be finished,
     * so homeroom teachers and subjects cannot be assigned out of order.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $requirement = null): Response
    {
        $academicYear = AcademicYear::current();

        if (! $academicYear) {
            return $this->deny($request, 'admin.tahun-ajaran.index', 'Tahun ajaran belum ada', 'Belum ada tahun ajaran aktif. Buat tahun ajaran terlebih dahulu.');
        }

        if ($requirement === 'promoted' && ! $academicYear->promotionCompleted()) {
            $awaiting = $academicYear->studentsAwaitingPromotion()->count();

            return $this->deny(
                $request,
                'admin.kenaikan.index',
                'Kenaikan kelas belum selesai',
                "Selesaikan proses kenaikan kelas tahun ajaran {$academicYear->year_name} terlebih dahulu ({$awaiting} siswa belum diproses). Wali kelas dan mata pelajaran baru bisa ditetapkan setelahnya.",
            );
        }

        $request->attributes->set('activeAcademicYear', $academicYear);

        return $next($request);
    }

    private function deny(Request $request, string $route, string $title, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_CONFLICT);
        }

        return redirect()->route($route)->with('alert', [
            'icon' => 'warning',
            'title' => $title,
            'text' => $message,
            'toast' => false,
        ]);
    }
}
