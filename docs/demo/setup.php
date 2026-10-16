<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use KanbanDemo\ProjectBoard;
use KanbanDemo\Task;
use KanbanDemo\User;

$database = __DIR__.'/database/demo.sqlite';
if (! is_dir(dirname($database))) {
    mkdir(dirname($database), 0755, true);
}
if (! file_exists($database)) {
    touch($database);
}

$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();

if (! Schema::hasTable('users')) {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->string('password')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
}

if (! Schema::hasTable('tasks')) {
    Schema::create('tasks', function (Blueprint $table): void {
        $table->id();
        $table->string('project');
        $table->string('title');
        $table->text('description');
        $table->string('status');
        $table->string('priority');
        $table->string('assignee');
        $table->date('due_date');
        $table->unsignedInteger('position');
        $table->timestamps();
        $table->index(['project', 'status', 'position', 'id']);
    });
}

User::firstOrCreate(['email' => 'demo@example.test'], ['name' => 'Alex Morgan']);

if (! Task::exists()) {
    $tasks = [
        ['Design the onboarding flow', 'Map the first steps for new workspace members.', 'backlog', 'high', 'Jamie Chen', '2026-10-20'],
        ['Add workspace invitations', 'Let teams invite collaborators with a link.', 'backlog', 'medium', 'Alex Morgan', '2026-10-22'],
        ['Write the launch checklist', 'Prepare a smooth release for the whole team.', 'backlog', 'low', 'Emma Brooks', '2026-10-24'],
        ['Review notification settings', 'Keep updates useful and easy to control.', 'backlog', 'medium', 'Sam Rivera', '2026-10-25'],
        ['Plan customer interviews', 'Collect feedback for the next iteration.', 'backlog', 'low', 'Jamie Chen', '2026-10-26'],
        ['Build the project dashboard', 'Bring milestones and team activity together.', 'in_progress', 'high', 'Alex Morgan', '2026-10-19'],
        ['Improve keyboard navigation', 'Make everyday workflows accessible to everyone.', 'in_progress', 'medium', 'Sam Rivera', '2026-10-21'],
        ['Polish the empty states', 'Help new users find their next step.', 'in_progress', 'low', 'Emma Brooks', '2026-10-23'],
        ['Set up the design tokens', 'A consistent foundation for every component.', 'done', 'medium', 'Jamie Chen', '2026-10-15'],
        ['Ship the activity feed', 'Keep everyone up to date on project progress.', 'done', 'high', 'Alex Morgan', '2026-10-16'],
        ['Document the public API', 'Clear examples for developers and integrations.', 'done', 'low', 'Sam Rivera', '2026-10-17'],
    ];

    foreach ($tasks as $index => [$title, $description, $status, $priority, $assignee, $dueDate]) {
        Task::create(['project' => 'launch', 'title' => $title, 'description' => $description, 'status' => $status, 'priority' => $priority, 'assignee' => $assignee, 'due_date' => $dueDate, 'position' => $index + 1]);
    }

    foreach (['backlog', 'in_progress', 'done'] as $columnIndex => $status) {
        foreach (array_chunk(range(1, 1000), 250) as $chunk) {
            Task::insert(array_map(fn (int $number): array => [
                'project' => 'scale',
                'title' => ['Backlog', 'In-progress', 'Completed'][$columnIndex].' task '.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                'description' => 'A fictional task from a project with 3,000 records.',
                'status' => $status,
                'priority' => ['high', 'medium', 'low'][$number % 3],
                'assignee' => array_keys(ProjectBoard::assignees())[$number % 4],
                'due_date' => '2026-10-22',
                'position' => $number,
                'created_at' => '2026-10-16 09:00:00',
                'updated_at' => '2026-10-16 09:00:00',
            ], $chunk));
        }
    }
}

Artisan::call('filament:assets');
echo 'Demo ready: '.Task::count()." fictional tasks.\n";
echo "Run: php -S 127.0.0.1:8879 -t public router.php\n";
