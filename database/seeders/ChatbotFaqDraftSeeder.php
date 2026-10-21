<?php

namespace Database\Seeders;

use App\Enums\ChatbotFaqStatus;
use App\Models\ChatbotFaq;
use Illuminate\Database\Seeder;

class ChatbotFaqDraftSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            [
                'intent' => 'document_request_procedure',
                'category' => 'Request procedure',
                'canonical_question' => 'Paano ako magre-request ng school document?',
                'question_examples' => [
                    'How do I request a school document?',
                    'Ano po ang proseso para kumuha ng dokumento?',
                    'Paano mag-request ng TOR?',
                    'Saan magsisimula ng document request?',
                ],
                'answer' => 'Draft: Hindi pa nakukumpirma ang opisyal na proseso para sa [PANGALAN NG DOKUMENTO]. '
                    .'Ilagay rito ang aprubadong mga hakbang, kung saan magsusumite, at kung paano matatanggap ang dokumento. '
                    .'Para sa tulong habang wala pang kumpirmadong sagot, kontakin ang [OPISYAL NA TANGGAPAN / CONTACT].',
            ],
            [
                'intent' => 'document_fee_tor',
                'category' => 'Fees',
                'canonical_question' => 'Magkano ang bayad para sa Transcript of Records (TOR)?',
                'question_examples' => [
                    'How much is the TOR?',
                    'Magkano po ang TOR?',
                    'Hm po ang bayad sa transcript?',
                    'Magkano yung Transcript of Records?',
                ],
                'answer' => 'Draft: Hindi pa nakukumpirma rito ang opisyal na TOR fee. '
                    .'Ilagay ang aprubadong halaga at currency dito: [APRUBADONG TOR FEE]. '
                    .'Beripikahin din ang petsa at opisyal na pinagmulan: [OFFICE / SOURCE / DATE].',
            ],
            [
                'intent' => 'document_fee_other',
                'category' => 'Fees',
                'canonical_question' => 'Magkano ang bayad para sa [PANGALAN NG DOKUMENTO]?',
                'question_examples' => [
                    'How much is [DOCUMENT]?',
                    'Magkano po ang bayad sa [DOCUMENT]?',
                    'Hm po yung [DOCUMENT]?',
                    'May bayad ba kumuha ng [DOCUMENT]?',
                ],
                'answer' => 'Draft: Hindi pa nakukumpirma rito ang opisyal na bayad para sa [PANGALAN NG DOKUMENTO]. '
                    .'Ilagay ang aprubadong halaga at currency: [APRUBADONG HALAGA]. '
                    .'Ilagay ang opisyal na pinagmulan at petsa ng beripikasyon: [OFFICE / SOURCE / DATE].',
            ],
            [
                'intent' => 'document_requirements',
                'category' => 'Requirements',
                'canonical_question' => 'Ano ang requirements para sa [PANGALAN NG DOKUMENTO]?',
                'question_examples' => [
                    'What are the requirements for [DOCUMENT]?',
                    'Ano pong kailangan para sa [DOCUMENT]?',
                    'Anong documents ang dadalhin ko?',
                    'May kailangan bang dalhin kapag magre-request?',
                ],
                'answer' => 'Draft: Hindi pa nakukumpirma rito ang opisyal na requirements para sa [PANGALAN NG DOKUMENTO]. '
                    .'Ilagay ang kumpleto at aprubadong listahan dito: [APRUBADONG REQUIREMENTS]. '
                    .'Beripikahin ang listahan sa [OPISYAL NA TANGGAPAN / SOURCE / DATE].',
            ],
            [
                'intent' => 'document_processing_time',
                'category' => 'Processing time',
                'canonical_question' => 'Gaano katagal maproseso ang [PANGALAN NG DOKUMENTO]?',
                'question_examples' => [
                    'How long does [DOCUMENT] take?',
                    'Ilang araw po bago makuha?',
                    'When will my document be ready?',
                    'Matagal po ba iprocess ang request?',
                ],
                'answer' => 'Draft: Hindi pa nakukumpirma rito ang opisyal na processing time para sa [PANGALAN NG DOKUMENTO]. '
                    .'Ilagay ang aprubadong tagal at kung working days o calendar days: [PROCESSING TIME]. '
                    .'Idagdag lamang ang mga aprubadong kondisyon o exception: [OFFICIAL CONDITIONS, IF ANY].',
            ],
            [
                'intent' => 'payment_instructions',
                'category' => 'Payment',
                'canonical_question' => 'Paano at saan magbabayad para sa document request?',
                'question_examples' => [
                    'How do I pay for my request?',
                    'Saan po magbabayad?',
                    'Paano po ang payment?',
                    'Pwede ba magbayad online?',
                ],
                'answer' => 'Draft: Hindi pa nakukumpirma rito ang opisyal na payment instructions. '
                    .'Ilagay lamang ang paraang aprubado ng paaralan: [OFFICIAL PAYMENT METHOD AND STEPS]. '
                    .'Para sa kumpirmadong detalye, kontakin ang [OPISYAL NA TANGGAPAN / CONTACT]. '
                    .'Hindi kinukumpirma ng chatbot na bayad na ang isang personal na request.',
            ],
            [
                'intent' => 'request_tracking_general',
                'category' => 'Request tracking',
                'canonical_question' => 'Paano ko matitingnan ang status ng document request ko?',
                'question_examples' => [
                    'How can I track my request?',
                    'Saan ko makikita ang status ng request?',
                    'Paano mag-check ng request status?',
                    'May tracking ba ang document request?',
                ],
                'answer' => 'Draft: Ilagay ang aprubadong pangkalahatang instructions kung saan ligtas tingnan ng student ang sariling request: '
                    .'[OFFICIAL AUTHENTICATED TRACKING STEPS]. Para sa tulong, kontakin ang [OPISYAL NA TANGGAPAN / CONTACT]. '
                    .'Hindi ipinapakita ng FAQ chatbot ang personal na request status.',
            ],
            [
                'intent' => 'account_login_help',
                'category' => 'Account / login',
                'canonical_question' => 'Hindi ako makapag-login. Saan ako hihingi ng tulong?',
                'question_examples' => [
                    'I cannot log in to SmartDoc.',
                    'Nakalimutan ko ang password ko.',
                    'Ayaw tanggapin ng system ang School ID ko.',
                    'How can I get help with my account?',
                ],
                'answer' => 'Draft: Para sa account assistance, sundin ang [APRUBADONG ACCOUNT RECOVERY STEPS] '
                    .'o kontakin ang [OPISYAL NA SUPPORT OFFICE / CONTACT]. Huwag ibahagi ang password o verification code '
                    .'sa chatbot o sa ibang tao.',
            ],
            [
                'intent' => 'office_contact',
                'category' => 'Office contact',
                'canonical_question' => 'Aling tanggapan ang dapat kong kontakin tungkol sa [PAKSA]?',
                'question_examples' => [
                    'Who should I contact about my document request?',
                    'Saan po ako magtatanong tungkol sa requirements?',
                    'Ano po contact ng office?',
                    'Where can I ask for help?',
                ],
                'answer' => 'Draft: Para sa [PAKSA], ilagay ang kumpirmadong pangalan ng opisina at contact details: '
                    .'[OPISYAL NA TANGGAPAN], [APRUBADONG EMAIL / TELEPONO / LOKASYON], [OFFICE HOURS, IF CONFIRMED].',
            ],
            [
                'intent' => 'request_status_private',
                'category' => 'Private status / escalation',
                'canonical_question' => 'Ano na ang status ng personal kong request?',
                'question_examples' => [
                    'Has my request been processed?',
                    'Ready na po ba yung request ko?',
                    'Na-release na ba ang document ko?',
                    'Can you check my personal request?',
                ],
                'answer' => 'Draft: Hindi kinukumpirma ng FAQ chatbot ang personal na status ng request. '
                    .'Tingnan ito sa [OPISYAL NA AUTHENTICATED TRACKING PAGE / STEPS] o kontakin ang '
                    .'[OPISYAL NA TANGGAPAN / CONTACT] para sa beripikadong impormasyon.',
            ],
            [
                'intent' => 'payment_status_private',
                'category' => 'Private status / escalation',
                'canonical_question' => 'Pumasok na ba ang bayad ko?',
                'question_examples' => [
                    'Can you confirm my payment?',
                    'Bayad na po ba ako sa system?',
                    'Nareceive na ba yung payment ko?',
                    'Please check my payment status.',
                ],
                'answer' => 'Draft: Hindi kinukumpirma ng FAQ chatbot ang personal na payment status. '
                    .'Gamitin ang [OPISYAL NA PAYMENT VERIFICATION CHANNEL] o kontakin ang '
                    .'[OPISYAL NA TANGGAPAN / CONTACT] para sa beripikadong impormasyon.',
            ],
        ];

        foreach ($faqs as $faq) {
            ChatbotFaq::firstOrCreate(
                ['intent' => $faq['intent'], 'locale' => 'fil'],
                $faq + [
                    'locale' => 'fil',
                    'status' => ChatbotFaqStatus::Draft,
                    'is_active' => false,
                    'is_suggested' => false,
                ]
            );
        }
    }
}
