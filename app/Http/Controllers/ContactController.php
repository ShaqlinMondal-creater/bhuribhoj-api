<?php

namespace App\Http\Controllers;

use App\Models\ActivityLogModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ContactController extends Controller
{
    public function contactSend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'mess' => ['required', 'string', 'min:2', 'max:160'],
            'members' => ['required', 'integer', 'min:1', 'max:5000'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $phone = trim((string) ($data['phone'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));

        if ($phone === '' && $email === '') {
            throw ValidationException::withMessages([
                'contact' => ['Enter a valid email or phone number.'],
            ]);
        }

        $inbox = (string) config('services.contact_inbox');
        if ($inbox === '') {
            throw ValidationException::withMessages([
                'message' => ['Contact inbox is not configured.'],
            ]);
        }

        Mail::send('mail.contact-inquiry', [
            'inquiry' => [
                'name' => $data['name'],
                'mess' => $data['mess'],
                'members' => $data['members'],
                'phone' => $phone !== '' ? $phone : null,
                'email' => $email !== '' ? $email : null,
                'message' => $data['message'],
            ],
        ], function ($message) use ($inbox, $data, $email) {
            $message->to($inbox)->subject('BhuriBhoj contact: '.$data['mess']);
            if ($email !== '') {
                $message->replyTo($email, $data['name']);
            }
        });

        ActivityLogModel::record(
            'contact.send',
            'Contact from '.$data['name'].' about '.$data['mess'].'.',
            null,
            'contact',
            null,
            null,
            [
                'name' => $data['name'],
                'mess' => $data['mess'],
                'members' => $data['members'],
                'phone' => $phone !== '' ? $phone : null,
                'email' => $email !== '' ? $email : null,
            ],
            $request->ip(),
        );

        return response()->json([
            'message' => 'Message sent. We will get back to you soon.',
        ]);
    }
}
