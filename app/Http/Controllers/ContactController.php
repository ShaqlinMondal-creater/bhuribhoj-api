<?php

namespace App\Http\Controllers;

use App\Models\ActivityLogModel;
use App\Models\ContactInquiryModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class ContactController extends Controller
{
    public function contactSend(Request $request): JsonResponse
    {
        // Honeypot: bots fill this; humans never see it. Same success shape, no store/mail.
        if (trim((string) $request->input('website', '')) !== '') {
            return response()->json([
                'message' => 'Message sent. We will get back to you soon.',
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'mess' => ['required', 'string', 'min:2', 'max:160'],
            'members' => ['required', 'integer', 'min:1', 'max:5000'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $data['name'] = $this->headerSafe($data['name']);
        $data['mess'] = $this->headerSafe($data['mess']);

        $phone = trim((string) ($data['phone'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));

        if ($phone === '' && $email === '') {
            throw ValidationException::withMessages([
                'contact' => ['Enter a valid email or phone number.'],
            ]);
        }

        $inbox = PlatformSettingsController::contactInbox();
        if ($inbox === '') {
            throw ValidationException::withMessages([
                'message' => ['Contact email is not set. Add public_contact_email in Admin → Configs.'],
            ]);
        }

        $inquiry = ContactInquiryModel::query()->create([
            'name' => $data['name'],
            'mess' => $data['mess'],
            'members' => $data['members'],
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email !== '' ? $email : null,
            'message' => $data['message'],
            'status' => 'open',
            'ip' => $request->ip(),
        ]);

        try {
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
        } catch (Throwable $e) {
            $inquiry->delete();
            throw $e;
        }

        ActivityLogModel::record(
            'contact.send',
            'Contact from '.$data['name'].' about '.$data['mess'].'.',
            null,
            'contact',
            $inquiry->id,
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

    private function headerSafe(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }
}
