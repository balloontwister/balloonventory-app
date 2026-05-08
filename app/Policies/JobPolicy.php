<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\Job;
use App\Models\User;
use App\Policies\Concerns\ChecksMembership;

class JobPolicy
{
    use ChecksMembership;

    public function viewAny(User $user, Business $business): bool
    {
        return $this->userCan($user, $business, 'job.view');
    }

    public function view(User $user, Job $job): bool
    {
        return $this->userCan($user, $job->business, 'job.view');
    }

    public function create(User $user, Business $business): bool
    {
        return $this->userCan($user, $business, 'job.create');
    }

    public function update(User $user, Job $job): bool
    {
        return $this->userCan($user, $job->business, 'job.edit');
    }

    public function delete(User $user, Job $job): bool
    {
        return $this->userCan($user, $job->business, 'job.delete');
    }

    public function setStatus(User $user, Job $job): bool
    {
        return $this->userCan($user, $job->business, 'job.set_status');
    }
}
