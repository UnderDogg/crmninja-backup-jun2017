<?php namespace App\Ninja\Repositories;

use Auth;
use Session;
use App\Models\Relation;
use App\Models\Task;

class TaskRepository
{
    public function find($relationPublicId = null, $filter = null)
    {
        $query = \DB::table('tasks')
                    ->leftJoin('relations', 'tasks.relation_id', '=', 'relations.id')
                    ->leftJoin('contacts', 'contacts.relation_id', '=', 'relations.id')
                    ->leftJoin('invoices', 'invoices.id', '=', 'tasks.invoice_id')
                    ->where('tasks.company_id', '=', Auth::user()->company_id)
                    ->where(function ($query) {
                        $query->where('contacts.is_primary', '=', true)
                                ->orWhere('contacts.is_primary', '=', null);
                    })
                    ->where('contacts.deleted_at', '=', null)
                    ->where('relations.deleted_at', '=', null)
                    ->select(
                        'tasks.public_id',
                        \DB::raw("COALESCE(NULLIF(relations.name,''), NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name),''), NULLIF(contacts.email,'')) relation_name"),
                        'relations.public_id as relation_public_id',
                        'relations.user_id as relation_user_id',
                        'contacts.first_name',
                        'contacts.email',
                        'contacts.last_name',
                        'invoices.invoice_status_id',
                        'tasks.description',
                        'tasks.is_deleted',
                        'tasks.deleted_at',
                        'invoices.invoice_number',
                        'invoices.public_id as invoice_public_id',
                        'invoices.user_id as invoice_user_id',
                        'invoices.balance',
                        'tasks.is_running',
                        'tasks.time_log',
                        'tasks.created_at',
                        'tasks.user_id'
                    );

        if ($relationPublicId) {
            $query->where('relations.public_id', '=', $relationPublicId);
        }

        if (!Session::get('show_trash:task')) {
            $query->where('tasks.deleted_at', '=', null);
        }

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('relations.name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.first_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.last_name', 'like', '%'.$filter.'%')
                      ->orWhere('tasks.description', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }

    public function save($publicId, $data, $task = null)
    {
        if ($task) {
            // do nothing
        } elseif ($publicId) {
            $task = Task::scope($publicId)->firstOrFail();
            \Log::warning('Entity not set in task repo save');
        } else {
            $task = Task::createNew();
        }

        if (isset($data['relation']) && $data['relation']) {
            $task->relation_id = Relation::getPrivateId($data['relation']);
        }
        if (isset($data['description'])) {
            $task->description = trim($data['description']);
        }

        if (isset($data['time_log'])) {
            $timeLog = json_decode($data['time_log']);
        } elseif ($task->time_log) {
            $timeLog = json_decode($task->time_log);
        } else {
            $timeLog = [];
        }

        array_multisort($timeLog);

        if (isset($data['action'])) {
            if ($data['action'] == 'start') {
                $task->is_running = true;
                $timeLog[] = [strtotime('now'), false];
            } else if ($data['action'] == 'resume') {
                $task->is_running = true;
                $timeLog[] = [strtotime('now'), false];
            } else if ($data['action'] == 'stop' && $task->is_running) {
                $timeLog[count($timeLog)-1][1] = time();
                $task->is_running = false;
            }
        }

        $task->time_log = json_encode($timeLog);
        $task->save();

        return $task;
    }

    public function bulk($ids, $action)
    {
        $tasks = Task::withTrashed()->scope($ids)->get();

        foreach ($tasks as $task) {
            if ($action == 'restore') {
                $task->restore();

                $task->is_deleted = false;
                $task->save();
            } else {
                if ($action == 'delete') {
                    $task->is_deleted = true;
                    $task->save();
                }

                $task->delete();
            }
        }

        return count($tasks);
    }
}
