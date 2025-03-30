<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all parent users
        $parents = User::where('role', 'parent')->get();

        if ($parents->isEmpty()) {
            $this->command->info('No parent users found. Skipping notification seeding.');
            return;
        }

        $this->command->info('Seeding notifications for ' . $parents->count() . ' parent users...');

        foreach ($parents as $parent) {
            $this->seedBillingNotifications($parent);
            $this->seedAcademicNotifications($parent);
            $this->seedMessageNotifications($parent);
            $this->seedSystemNotifications($parent);
            $this->seedScheduleNotifications($parent);
        }

        $this->command->info('Notifications seeded successfully!');
    }

    /**
     * Seed billing notifications
     */
    private function seedBillingNotifications(User $parent)
    {
        // New invoice notification (unread)
        Notification::create([
            'user_id' => $parent->id,
            'type' => 'billing',
            'title' => 'New Invoice Generated',
            'message' => "A new invoice (#INV-" . rand(1000, 9999) . ") has been generated for your account. Please review and complete the payment by " . Carbon::now()->addDays(7)->format('M d, Y') . ".\n\nInvoice Amount: $" . rand(50, 200) . ".00",
            'created_at' => Carbon::now()->subHours(rand(1, 12)),
            'action_text' => 'View Invoice',
            'action_url' => '/parents/invoices',
            'metadata' => [
                'invoice_id' => rand(1000, 9999),
                'amount' => '$' . rand(50, 200) . '.00',
                'due_date' => Carbon::now()->addDays(7)->format('Y-m-d'),
            ],
        ]);

        // Payment confirmation (read)
        Notification::create([
            'user_id' => $parent->id,
            'type' => 'billing',
            'title' => 'Payment Successful',
            'message' => "Your payment of $" . rand(50, 200) . ".00 has been successfully processed. Thank you for your payment!\n\nTransaction ID: TXN-" . strtoupper(substr(md5(rand()), 0, 8)),
            'created_at' => Carbon::now()->subDays(rand(1, 15)),
            'read_at' => Carbon::now()->subDays(rand(1, 10)),
            'metadata' => [
                'transaction_id' => 'TXN-' . strtoupper(substr(md5(rand()), 0, 8)),
                'payment_method' => 'Credit Card',
                'amount' => '$' . rand(50, 200) . '.00',
            ],
        ]);

        // Payment reminder (unread)
        if (rand(0, 1)) {
            Notification::create([
                'user_id' => $parent->id,
                'type' => 'billing',
                'title' => 'Payment Reminder',
                'message' => "This is a friendly reminder that invoice #INV-" . rand(1000, 9999) . " is due on " . Carbon::now()->addDays(3)->format('M d, Y') . ".\n\nAmount Due: $" . rand(50, 200) . ".00",
                'created_at' => Carbon::now()->subHours(rand(18, 48)),
                'action_text' => 'Pay Now',
                'action_url' => '/parents/invoices',
                'metadata' => [
                    'invoice_id' => rand(1000, 9999),
                    'amount' => '$' . rand(50, 200) . '.00',
                    'due_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
                ],
            ]);
        }
    }

    /**
     * Seed academic notifications
     */
    private function seedAcademicNotifications(User $parent)
    {
        // Assessment results
        Notification::create([
            'user_id' => $parent->id,
            'type' => 'academic',
            'title' => 'Assessment Results Available',
            'message' => "New assessment results are available for your child.\n\nSubject: " . $this->getRandomSubject() . "\nScore: " . rand(70, 100) . "%\nTeacher Comments: " . $this->getRandomComment(),
            'created_at' => Carbon::now()->subDays(rand(1, 10)),
            'read_at' => rand(0, 1) ? Carbon::now()->subDays(rand(1, 5)) : null,
            'action_text' => 'View Results',
            'action_url' => '/parents/progress',
            'metadata' => [
                'subject' => $this->getRandomSubject(),
                'score' => rand(70, 100) . '%',
                'assessment_date' => Carbon::now()->subDays(rand(1, 10))->format('Y-m-d'),
            ],
        ]);

        // Progress update
        Notification::create([
            'user_id' => $parent->id,
            'type' => 'academic',
            'title' => 'Monthly Progress Report',
            'message' => "Your child's monthly progress report is now available. Overall, they've shown significant improvement in " . $this->getRandomSubject() . " and " . $this->getRandomSubject() . ".\n\nView the detailed report to see specific areas of strength and opportunities for growth.",
            'created_at' => Carbon::now()->subDays(rand(3, 20)),
            'read_at' => rand(0, 1) ? Carbon::now()->subDays(rand(1, 10)) : null,
            'action_text' => 'View Report',
            'action_url' => '/parents/reports',
        ]);

        // Homework notification
        if (rand(0, 1)) {
            Notification::create([
                'user_id' => $parent->id,
                'type' => 'homework',
                'title' => 'New Homework Assigned',
                'message' => "New homework has been assigned for your child.\n\nSubject: " . $this->getRandomSubject() . "\nDue Date: " . Carbon::now()->addDays(rand(2, 7))->format('M d, Y') . "\nDescription: " . $this->getRandomHomework(),
                'created_at' => Carbon::now()->subHours(rand(1, 24)),
                'action_text' => 'View Homework',
                'action_url' => '/parents/homework',
                'metadata' => [
                    'subject' => $this->getRandomSubject(),
                    'due_date' => Carbon::now()->addDays(rand(2, 7))->format('Y-m-d'),
                ],
            ]);
        }
    }

    /**
     * Seed message notifications
     */
    private function seedMessageNotifications(User $parent)
    {
        // New message from teacher
        Notification::create([
            'user_id' => $parent->id,
            'type' => 'message',
            'title' => 'New Message from Teacher',
            'message' => "You have received a new message from " . $this->getRandomTeacherName() . ":\n\n\"" . $this->getRandomTeacherMessage() . "\"",
            'created_at' => Carbon::now()->subHours(rand(1, 24)),
            'read_at' => rand(0, 1) ? Carbon::now()->subHours(rand(1, 12)) : null,
            'action_text' => 'Reply',
