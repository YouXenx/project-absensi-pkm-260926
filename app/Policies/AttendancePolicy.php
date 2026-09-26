<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Admin: every attendance record, any date (koreksi absensi).
 * Guru: only records of subjects they teach, and may change them only on the same day.
 */
class AttendancePolicy
{
    public const PAST_DATE_MESSAGE = 'Guru hanya bisa mengubah absensi hari ini. Absensi tanggal lampau hanya bisa dikoreksi admin.';

    public function view(User $user, Attendance $attendance): bool
    {
        return $user->isAdmin() || $this->teaches($user, $attendance->subject);
    }

    public function update(User $user, Attendance $attendance): Response
    {
        if ($user->isAdmin()) {
            return Response::allow();
        }

        if (! $this->teaches($user, $attendance->subject)) {
            return Response::deny();
        }

        return $attendance->isToday()
            ? Response::allow()
            : Response::deny(self::PAST_DATE_MESSAGE);
    }

    /**
     * Removing a record is a correction, reserved for admins.
     */
    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->isAdmin();
    }

    /**
     * Taking attendance for a subject: its own guru, only in the active academic year, and only once the
     * yearly flow has reached the subject (promotion finished, class has a homeroom teacher).
     */
    public function take(User $user, Subject $subject): Response
    {
        if (! $this->teaches($user, $subject)) {
            return Response::deny();
        }

        $academicYear = $subject->academicYear;

        if (! $academicYear->is_active) {
            return Response::deny('Absensi hanya bisa diisi untuk mata pelajaran di tahun ajaran aktif.');
        }

        if (! $academicYear->promotionCompleted()) {
            return Response::deny("Absensi belum dibuka: proses kenaikan kelas tahun ajaran {$academicYear->year_name} belum selesai.");
        }

        if (! $subject->hasHomeroomTeacher()) {
            return Response::deny("Absensi belum dibuka: {$subject->schoolClass->class_name} belum punya wali kelas di tahun ajaran {$academicYear->year_name}.");
        }

        return Response::allow();
    }

    private function teaches(User $user, Subject $subject): bool
    {
        return $user->isGuru() && $subject->user_id === $user->id;
    }
}
