<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChatbotFaqStatus;
use App\Http\Controllers\Controller;
use App\Models\ChatbotFaq;
use App\Models\ChatbotInquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ChatbotFaqController extends Controller
{
    public function index(Request $request): View
    {
        $faqs = ChatbotFaq::query()
            ->when($request->boolean('archived'), fn ($query) => $query->archived(), fn ($query) => $query->unarchived())
            ->with(['creator', 'approver'])
            ->orderBy('intent')
            ->orderBy('locale')
            ->paginate(20);

        $recommendations = ChatbotInquiry::query()
            ->select([
                'suggested_intent',
                'locale',
                DB::raw('MIN(suggested_question) AS suggested_question'),
                DB::raw('COUNT(*) AS inquiry_count'),
                DB::raw('MAX(created_at) AS last_inquired_at'),
            ])
            ->whereNotNull('suggested_intent')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('suggested_intent', 'locale')
            ->havingRaw('COUNT(*) >= ?', [3])
            ->orderByDesc('inquiry_count')
            ->limit(20)
            ->get()
            ->filter(fn ($recommendation) => ! ChatbotFaq::query()
                ->where('intent', $recommendation->suggested_intent)
                ->where('locale', $recommendation->locale)
                ->exists())
            ->values();

        return view('admin.chatbot-faqs.index', compact('faqs', 'recommendations'));
    }

    public function createRecommendation(Request $request, string $intent): RedirectResponse
    {
        abort_unless(preg_match('/^[a-z][a-z0-9_]{2,79}$/', $intent), 404);

        $locale = $request->validate(['locale' => ['required', 'in:fil,en']])['locale'];
        $recommendation = ChatbotInquiry::query()
            ->where('suggested_intent', $intent)
            ->where('locale', $locale)
            ->where('created_at', '>=', now()->subDays(30))
            ->select([
                'suggested_intent',
                'locale',
                DB::raw('MIN(suggested_question) AS suggested_question'),
                DB::raw('COUNT(*) AS inquiry_count'),
            ])
            ->groupBy('suggested_intent', 'locale')
            ->havingRaw('COUNT(*) >= ?', [3])
            ->first();

        if (! $recommendation) {
            return back()->withErrors(['recommendation' => 'This topic no longer meets the recommendation threshold.']);
        }

        if (ChatbotFaq::query()->where('intent', $intent)->where('locale', $locale)->exists()) {
            return back()->withErrors(['recommendation' => 'An FAQ already exists for this intent and language.']);
        }

        $faq = ChatbotFaq::create([
            'intent' => $intent,
            'category' => 'Needs admin review',
            'canonical_question' => $recommendation->suggested_question,
            'question_examples' => [],
            'answer' => 'Draft only. Add an answer confirmed by the responsible school office before approval.',
            'locale' => $locale,
            'status' => \App\Enums\ChatbotFaqStatus::Draft,
            'is_active' => false,
            'is_suggested' => false,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.chatbot-faqs.edit', $faq)
            ->with('status', 'FAQ draft created from an anonymous recurring-topic recommendation.');
    }

    public function create(): View
    {
        return view('admin.chatbot-faqs.form', ['faq' => new ChatbotFaq]);
    }

    public function store(Request $request): RedirectResponse
    {
        $faq = ChatbotFaq::create($this->validatedFaq($request) + [
            'status' => ChatbotFaqStatus::Draft,
            'is_active' => false,
            'is_suggested' => $request->boolean('is_suggested'),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.chatbot-faqs.index')
            ->with('status', "FAQ {$faq->intent} created as a draft.");
    }

    public function edit(ChatbotFaq $chatbotFaq): View
    {
        return view('admin.chatbot-faqs.form', ['faq' => $chatbotFaq]);
    }

    public function update(Request $request, ChatbotFaq $chatbotFaq): RedirectResponse
    {
        abort_if($chatbotFaq->archived_at !== null, 409, 'Restore the FAQ before editing it.');

        $chatbotFaq->fill($this->validatedFaq($request, $chatbotFaq));
        $chatbotFaq->is_suggested = $request->boolean('is_suggested');

        if ($chatbotFaq->isDirty([
            'intent', 'category', 'canonical_question', 'question_examples', 'answer', 'locale',
        ])) {
            $chatbotFaq->status = ChatbotFaqStatus::PendingApproval;
            $chatbotFaq->is_active = false;
            $chatbotFaq->approved_by = null;
            $chatbotFaq->approved_at = null;
        }

        $chatbotFaq->save();

        return redirect()->route('admin.chatbot-faqs.index')
            ->with('status', 'FAQ updated. Any content changes require approval before the answer is shown.');
    }

    public function approve(Request $request, ChatbotFaq $chatbotFaq): RedirectResponse
    {
        abort_if($chatbotFaq->archived_at !== null, 409, 'Restore the FAQ before approving it.');

        abort_if($chatbotFaq->status === ChatbotFaqStatus::Rejected, 422, 'Rejected FAQs must be edited before approval.');

        $chatbotFaq->update([
            'status' => ChatbotFaqStatus::Approved,
            'is_active' => false,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('status', 'FAQ approved. Activate it when it is ready to appear in the chatbot.');
    }

    public function setActive(Request $request, ChatbotFaq $chatbotFaq): RedirectResponse
    {
        abort_if($chatbotFaq->archived_at !== null, 409, 'Restore the FAQ before changing its availability.');

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        if ($validated['is_active'] && $chatbotFaq->status !== ChatbotFaqStatus::Approved) {
            return back()->withErrors([
                'is_active' => 'Only approved FAQs can be activated.',
            ]);
        }

        $chatbotFaq->update(['is_active' => (bool) $validated['is_active']]);

        return back()->with('status', 'FAQ availability updated.');
    }

    public function archive(ChatbotFaq $chatbotFaq): RedirectResponse
    {
        $chatbotFaq->update(['is_active' => false]);
        $chatbotFaq->archive();

        return back()->with('status', 'FAQ archived and removed from chatbot answers.');
    }

    public function restore(ChatbotFaq $chatbotFaq): RedirectResponse
    {
        $chatbotFaq->restoreArchive();

        return back()->with('status', 'FAQ restored. Review its approval and activation before making it available.');
    }

    public function bulkArchive(Request $request): RedirectResponse
    {
        $faqs = $this->selectedFaqs($request, false);
        DB::transaction(function () use ($faqs) {
            foreach ($faqs as $faq) {
                $faq->update(['is_active' => false]);
                $faq->archive();
            }
        });

        return back()->with('status', $faqs->count().' FAQ(s) archived.');
    }

    public function bulkRestore(Request $request): RedirectResponse
    {
        $faqs = $this->selectedFaqs($request, true);
        DB::transaction(fn () => $faqs->each(fn (ChatbotFaq $faq) => $faq->restoreArchive()));

        return back()->with('status', $faqs->count().' FAQ(s) restored.');
    }

    private function selectedFaqs(Request $request, bool $archived)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $query = ChatbotFaq::whereIn('id', $data['ids']);
        $archived ? $query->archived() : $query->unarchived();
        $faqs = $query->get();
        if ($faqs->count() !== count($data['ids'])) {
            throw ValidationException::withMessages([
                'ids' => 'Some selected FAQs are no longer available for this action.',
            ]);
        }

        return $faqs;
    }

    private function validatedFaq(Request $request, ?ChatbotFaq $faq = null): array
    {
        $intentRule = Rule::unique('chatbot_faqs', 'intent')
            ->where('locale', $request->input('locale'));
        if ($faq !== null) {
            $intentRule->ignore($faq->id);
        }

        $validated = $request->validate([
            'intent' => [
                'required',
                'string',
                'regex:/^[a-z][a-z0-9_]{2,79}$/',
                $intentRule,
            ],
            'category' => ['required', 'string', 'max:100'],
            'canonical_question' => ['required', 'string', 'max:255'],
            'question_examples' => ['nullable', 'string', 'max:4000'],
            'answer' => ['required', 'string', 'max:5000'],
            'locale' => ['required', 'in:fil,en'],
        ]);

        $examples = preg_split('/\R/u', trim($validated['question_examples'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);

        return [
            'intent' => $validated['intent'],
            'category' => $validated['category'],
            'canonical_question' => $validated['canonical_question'],
            'question_examples' => array_values(array_unique(array_map('trim', $examples ?: []))),
            'answer' => $validated['answer'],
            'locale' => $validated['locale'],
        ];
    }
}
