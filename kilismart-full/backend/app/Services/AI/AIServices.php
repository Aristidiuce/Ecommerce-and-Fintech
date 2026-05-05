<?php

namespace App\Services\AI;

use App\Models\{User, Product, SavingPlan, Transaction};
use Illuminate\Support\Facades\{Cache, Log, Http};

// ============================================================
//  KiliSmart AI Services — Version 1.0
//
//  These services provide AI-powered features using rule-based
//  logic in Phase 1, with room to plug in real AI models
//  (Claude API, OpenAI, or local LLM) in Phase 2.
//
//  API ROOM: Each service has an ->useExternalAI() method
//  that can be uncommented to use the Anthropic Claude API.
// ============================================================


// ════════════════════════════════════════════════════════════
//  1. SMART SAVINGS ADVISOR
//  Analyzes a customer's income, job type, and saving history
//  to recommend the optimal weekly deposit amount and timeline.
// ════════════════════════════════════════════════════════════
class SmartSavingsAdvisor
{
    // ── Income bands (TZS per month) ──────────────────────────
    protected array $incomeBands = [
        'Chini ya TZS 200,000'       => ['min'=>0,    'max'=>200000, 'save_pct'=>0.08],
        'TZS 200,000 – 500,000'      => ['min'=>200000,'max'=>500000, 'save_pct'=>0.12],
        'TZS 500,000 – 1,000,000'    => ['min'=>500000,'max'=>1000000,'save_pct'=>0.15],
        'Zaidi ya TZS 1,000,000'     => ['min'=>1000000,'max'=>PHP_INT_MAX,'save_pct'=>0.18],
    ];

    // ── Job-type adjustments ──────────────────────────────────
    protected array $jobMultipliers = [
        'Mkulima'         => 0.85, // seasonal income — save less weekly
        'Biashara ndogo'  => 1.00, // baseline
        'Mwajiriwa'       => 1.10, // steady income — can save more
        'Bodaboda'        => 0.90, // variable income
        'Mwalimu'         => 1.15, // government salary — very steady
        'Fundi'           => 0.95,
        'Daktari/Muuguzi' => 1.20,
        'Nyingine'        => 1.00,
    ];

    public function advise(User $user, int $productPrice): array
    {
        $band    = $this->incomeBands[$user->income_range] ?? $this->incomeBands['TZS 200,000 – 500,000'];
        $mult    = $this->jobMultipliers[$user->job_type] ?? 1.00;
        $avgIncome = ($band['min'] + $band['max']) / 2;
        if ($avgIncome > 2000000) $avgIncome = 2000000; // cap

        // Monthly saving capacity
        $monthlySavings = (int) ($avgIncome * $band['save_pct'] * $mult);
        $weeklySavings  = (int) ($monthlySavings / 4.33);
        $weeklySavings  = max(2000, $weeklySavings); // minimum TZS 2,000

        // Calculate weeks to complete
        $weeks = (int) ceil($productPrice / $weeklySavings);

        // Suggestions at 3 levels
        $conservative = (int) ceil($productPrice / ($weeks * 1.5));
        $recommended  = $weeklySavings;
        $aggressive   = (int) ceil($productPrice / max(4, $weeks * 0.6));

        // Payday alignment
        $paydayTip = $this->paydayTip($user->payday_cycle);

        return [
            'recommended_weekly' => $recommended,
            'weeks_at_recommended' => $weeks,
            'conservative_weekly' => max(2000, $conservative),
            'aggressive_weekly' => $aggressive,
            'monthly_capacity' => $monthlySavings,
            'income_band' => $user->income_range,
            'job_adjustment' => $mult,
            'payday_tip' => $paydayTip,
            'scenarios' => $this->buildScenarios($productPrice),
            'advice_text' => $this->buildAdviceText($user, $recommended, $weeks, $productPrice),
        ];
    }

    protected function buildScenarios(int $price): array
    {
        return collect([4, 8, 12, 16, 24, 36, 52])->map(fn($w) => [
            'weeks'      => $w,
            'per_week'   => (int) ceil($price / $w),
            'per_day'    => (int) ceil($price / ($w * 7)),
            'per_month'  => (int) ceil($price / ($w / 4.33)),
        ])->all();
    }

    protected function paydayTip(?string $cycle): string
    {
        return match($cycle) {
            'Mwisho wa Mwezi' => 'Weka pesa siku ya kwanza ya mwezi — wakati mshahara wako ukifika.',
            'Wiki ya Kwanza'  => 'Weka pesa kila Jumatatu asubuhi — kawaida wiki inayofuata malipo.',
            'Kila Wiki'       => 'Weka pesa kila Ijumaa mwisho wa wiki ya kazi.',
            default           => 'Weka pesa mara yoyote unapopata — hata TZS 2,000 inasaidia.',
        };
    }

    protected function buildAdviceText(User $user, int $weekly, int $weeks, int $price): string
    {
        $name = explode(' ', $user->full_name)[0];
        return "Habari {$name}! Kulingana na kazi yako ({$user->job_type}) na mapato yako, "
            . "napendekeza uweke TZS " . number_format($weekly) . " kwa wiki. "
            . "Utamaliza kwa wiki {$weeks} — karibu miezi " . round($weeks/4.33, 1) . ". "
            . "Hii ni " . round($weekly / ($price / 100), 1) . "% ya bei yote kwa wiki moja. "
            . $this->paydayTip($user->payday_cycle);
    }

    // ── FUTURE: Plug in Claude API for personalized advice ────
    // public function adviseWithAI(User $user, int $price): array
    // {
    //     $response = Http::withHeaders(['x-api-key' => config('ai.anthropic_key')])
    //         ->post('https://api.anthropic.com/v1/messages', [
    //             'model' => 'claude-opus-4-5',
    //             'max_tokens' => 500,
    //             'messages' => [[
    //                 'role' => 'user',
    //                 'content' => "You are KiliBot, a savings advisor in Tanzania speaking Swahili.
    //                     Customer: {$user->full_name}, Job: {$user->job_type},
    //                     Income: {$user->income_range}, Product price: TZS {$price}.
    //                     Give personalized savings advice in Swahili, 3 sentences max."
    //             ]]
    //         ]);
    //     return ['ai_advice' => $response->json('content.0.text')];
    // }
}


// ════════════════════════════════════════════════════════════
//  2. PRODUCT RECOMMENDER
//  Suggests products based on customer's profile, district,
//  saving history, and what similar customers buy.
// ════════════════════════════════════════════════════════════
class ProductRecommender
{
    public function recommend(User $user, int $limit = 6): array
    {
        // Products the user is already saving for
        $alreadySaving = SavingPlan::where('user_id', $user->id)
            ->whereIn('status', ['active', 'completed'])
            ->pluck('product_id');

        $scores = Product::where('status', 'active')
            ->whereNotIn('id', $alreadySaving)
            ->get()
            ->map(fn($p) => [
                'product'  => $p,
                'score'    => $this->scoreProduct($p, $user),
            ])
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        return $scores->map(fn($item) => array_merge(
            $item['product']->toArray(),
            ['recommendation_score' => $item['score'], 'reason' => $this->reason($item['product'], $user)]
        ))->all();
    }

    protected function scoreProduct(Product $p, User $user): float
    {
        $score = 50.0; // base score

        // Job-category affinity
        $affinities = [
            'Mkulima'        => ['kilimo-farming' => +20, 'solar-energy' => +10],
            'Biashara ndogo' => ['smart-phones' => +15, 'computers-tv' => +10],
            'Mwajiriwa'      => ['home-appliances' => +10, 'furniture' => +8],
            'Bodaboda'       => ['boda-vehicles' => +25, 'smart-phones' => +10],
            'Mwalimu'        => ['computers-tv' => +15, 'school-kids' => +20],
        ];

        $jobAffinities = $affinities[$user->job_type] ?? [];
        $score += $jobAffinities[$p->category?->slug] ?? 0;

        // Income vs price fit (prefer products within reach)
        $incomeMax = $this->incomeMax($user->income_range);
        $ratio = $p->retail_price / $incomeMax;
        if ($ratio < 0.5) $score += 15;       // very affordable
        elseif ($ratio < 1.0) $score += 10;   // affordable
        elseif ($ratio < 2.0) $score += 5;    // stretch goal — good
        else $score -= 10;                      // too expensive right now

        // Popularity boost (active savers)
        $savers = SavingPlan::where('product_id', $p->id)->where('status', 'active')->count();
        $score += min(15, $savers * 0.2); // up to +15

        // Badge boost
        if ($p->badge === 'sale') $score += 8;
        if ($p->badge === 'new')  $score += 5;

        return $score;
    }

    protected function incomeMax(string $range): int
    {
        return match(true) {
            str_contains($range, '1,000,000') => 2000000,
            str_contains($range, '500,000')   => 1000000,
            str_contains($range, '200,000')   => 500000,
            default                            => 200000,
        };
    }

    protected function reason(Product $p, User $user): string
    {
        $reasons = [
            'Mkulima'        => ['kilimo-farming' => 'Inafaa sana kwa shamba lako'],
            'Biashara ndogo' => ['smart-phones'   => 'Itasaidia mawasiliano ya biashara yako'],
            'Bodaboda'       => ['boda-vehicles'  => 'Bidhaa maarufu kwa madereva wa bodaboda'],
            'Mwalimu'        => ['school-kids'     => 'Inafaa kwa wanafunzi wako'],
        ];

        $r = $reasons[$user->job_type][$p->category?->slug] ?? null;
        if ($r) return $r;

        $savers = SavingPlan::where('product_id', $p->id)->where('status', 'active')->count();
        if ($savers > 50) return "Inapendwa na watu {$savers} — popular sana!";
        if ($p->badge === 'sale') return 'Bei imepunguzwa — wakati mzuri!';
        return 'Inayofaa kwa hali yako';
    }

    // ── FUTURE: Collaborative filtering with ML model ─────────
    // public function recommendWithML(User $user): array
    // {
    //     // Feed user embeddings to a recommendation model
    //     // Return top-N product IDs based on similar user behaviour
    // }
}


// ════════════════════════════════════════════════════════════
//  3. NATURAL LANGUAGE SEARCH
//  Understands queries like "simu ya bei nafuu" or "jiko la
//  umeme" and maps them to relevant products.
// ════════════════════════════════════════════════════════════
class NaturalLanguageSearch
{
    // Keyword → category/product mapping (Swahili + English)
    protected array $categoryMap = [
        'simu'     => 'smart-phones', 'phone'   => 'smart-phones',
        'samsung'  => 'smart-phones', 'tecno'   => 'smart-phones',
        'itel'     => 'smart-phones', 'android' => 'smart-phones',
        'laptop'   => 'computers-tv', 'kompyuta'=> 'computers-tv',
        'lenovo'   => 'computers-tv', 'tv'      => 'computers-tv',
        'solar'    => 'solar-energy', 'jua'     => 'solar-energy',
        'taa'      => 'solar-energy', 'betri'   => 'solar-energy',
        'godoro'   => 'home-appliances','kitanda'=> 'home-appliances',
        'sufuria'  => 'home-appliances','jiko'   => 'home-appliances',
        'sofa'     => 'furniture',    'kiti'    => 'furniture',
        'meza'     => 'furniture',    'samani'  => 'furniture',
        'kilimo'   => 'kilimo-farming','shamba'  => 'kilimo-farming',
        'mkulima'  => 'kilimo-farming','pampu'   => 'kilimo-farming',
        'baiskeli' => 'boda-vehicles', 'bodaboda'=> 'boda-vehicles',
        'shule'    => 'school-kids',  'mtoto'   => 'school-kids',
        'mfuko'    => 'school-kids',
    ];

    protected array $priceIntentMap = [
        'nafuu'      => ['max' => 100000],
        'cheap'      => ['max' => 100000],
        'gharama'    => ['min' => 300000],
        'expensive'  => ['min' => 300000],
        'wastani'    => ['min' => 100000, 'max' => 300000],
        'mid'        => ['min' => 100000, 'max' => 300000],
    ];

    public function search(string $query): array
    {
        $tokens = array_unique(array_filter(
            explode(' ', strtolower(trim($query)))
        ));

        $categoryFilter = null;
        $priceFilter    = [];
        $keywords       = [];

        foreach ($tokens as $token) {
            if (isset($this->categoryMap[$token])) {
                $categoryFilter = $this->categoryMap[$token];
            } elseif (isset($this->priceIntentMap[$token])) {
                $priceFilter = array_merge($priceFilter, $this->priceIntentMap[$token]);
            } else {
                $keywords[] = $token;
            }
        }

        $q = Product::where('status', 'active');

        if ($categoryFilter) {
            $q->whereHas('category', fn($cq) => $cq->where('slug', $categoryFilter));
        }

        if (!empty($keywords)) {
            $q->where(function ($sq) use ($keywords) {
                foreach ($keywords as $kw) {
                    $sq->orWhere('name', 'like', "%{$kw}%")
                       ->orWhere('name_sw', 'like', "%{$kw}%")
                       ->orWhere('description_sw', 'like', "%{$kw}%");
                }
            });
        }

        if (!empty($priceFilter['min'])) $q->where('retail_price', '>=', $priceFilter['min']);
        if (!empty($priceFilter['max'])) $q->where('retail_price', '<=', $priceFilter['max']);

        return [
            'products'        => $q->with('category')->get(),
            'detected_category'=> $categoryFilter,
            'price_intent'    => $priceFilter,
            'query_tokens'    => $tokens,
            'suggestion'      => $this->buildSuggestion($query, $categoryFilter),
        ];
    }

    protected function buildSuggestion(string $q, ?string $cat): string
    {
        if ($cat) return "Unaonyesha bidhaa za " . ucfirst(str_replace('-', ' ', $cat));
        return "Matokeo ya: \"{$q}\"";
    }

    // ── FUTURE: Vector embedding search with semantic similarity ─
    // public function semanticSearch(string $query): array
    // {
    //     $embedding = $this->getEmbedding($query);  // OpenAI/Claude embeddings
    //     // Compare against pre-computed product embeddings stored in DB
    //     // Return products sorted by cosine similarity
    // }
}


// ════════════════════════════════════════════════════════════
//  4. PRICE TREND PREDICTOR
//  Analyzes product price history to predict if prices will
//  go up or down, helping customers time their savings plans.
// ════════════════════════════════════════════════════════════
class PriceTrendPredictor
{
    public function predict(Product $product): array
    {
        // In Phase 1: use simple heuristics
        // In Phase 2: use historical price data from price_history table
        // In Phase 3: use time-series ML model

        $badge = $product->badge;
        $daysSinceCreated = $product->created_at->diffInDays(now());

        // Heuristic prediction
        [$trend, $confidence, $recommendation] = match(true) {
            $badge === 'sale' => [
                'down_temporary',
                0.85,
                'Bei imeshuka kwa muda! Hii ni wakati mzuri sana kuanza akiba. Punguzo linaweza kuisha wakati wowote.'
            ],
            $badge === 'new' && $daysSinceCreated < 30 => [
                'stable',
                0.70,
                'Bidhaa mpya — bei kawaida inakuwa imara kwa miezi 2-3 ya kwanza.'
            ],
            $product->retail_price > 500000 => [
                'down_likely',
                0.60,
                'Bidhaa za bei kubwa mara nyingi hupungukiwa bei baada ya miezi 3-6. Lakini usisubiri sana — bei lock yako inadumu siku 60!'
            ],
            default => [
                'stable',
                0.65,
                'Bei imekuwa imara. Wakati wowote ni mzuri kuanza akiba yako.'
            ],
        };

        return [
            'product_id'       => $product->id,
            'current_price'    => $product->retail_price,
            'trend'            => $trend,
            'confidence'       => $confidence,
            'recommendation'   => $recommendation,
            'price_lock_days'  => $product->price_lock_days,
            'price_lock_until' => now()->addDays($product->price_lock_days)->format('d M Y'),
            'urgency_score'    => $this->urgencyScore($trend, $confidence),
        ];
    }

    protected function urgencyScore(string $trend, float $confidence): int
    {
        // 1-10 score of how urgently to start saving
        return match($trend) {
            'down_temporary' => min(10, (int)($confidence * 10)),
            'up_likely'      => 9,
            'stable'         => 6,
            'down_likely'    => 4,
            default          => 5,
        };
    }

    // ── FUTURE: Real time-series prediction with price history ─
    // public function predictWithHistory(Product $product): array
    // {
    //     $history = PriceHistory::where('product_id', $product->id)
    //         ->orderBy('recorded_at')->get();
    //     // Feed to Prophet or ARIMA model
    //     // Return predicted price for next 30/60/90 days
    // }
}


// ════════════════════════════════════════════════════════════
//  5. AI CUSTOMER SUPPORT
//  Answers common customer questions automatically in Swahili.
//  Escalates to human if confidence is low.
// ════════════════════════════════════════════════════════════
class CustomerSupportAI
{
    protected array $knowledgeBase = [
        // Deposits
        ['q'=>['weka pesa','deposit','amana','m-pesa','tigo','airtel','lipa'],
         'a'=>"Unaweza kuweka pesa kupitia M-Pesa, Tigo Pesa, au Airtel Money.\n\nKwa M-Pesa: Lipa Paybill nambari yetu, weka nambari yako ya simu kama akaunti.\nKiwango cha chini: TZS 2,000 tu.\n\nPesa itaingia kwenye wallet yako moja kwa moja! 💰"],

        // Withdrawal
        ['q'=>['toa pesa','withdrawal','kutoa','rudisha','pesa yangu'],
         'a'=>"Unaweza kutoa pesa wakati wowote! Hata hivyo:\n\n• Ada ya 5% inakatwa\n• Kiwango cha chini: TZS 5,000\n• Lazima upumzike siku 7 baada ya amana ya mwisho\n\nOmba kwenye dashboard yako → Wallet → Toa Pesa. Idhini inafika masaa 24. 🔄"],

        // Delivery
        ['q'=>['delivery','lini bidhaa','itafika lini','usafirishaji','pokea'],
         'a'=>"Delivery inafanywa baada ya KUKIFIKA 100% ya lengo lako:\n\n📍 Moshi Mjini: Siku 1-3\n📍 Hai, Rombo, Siha: Siku 3-5\n📍 Mwanga, Same: Siku 5-7\n\nUtapata WhatsApp ukifika 100%, na picha ya bidhaa kabla haijafika kwako! 🚚"],

        // Plans
        ['q'=>['mpango','saving plan','anza kuhifadhi','jinsi','vipi','bei'],
         'a'=>"Jinsi KiliSmart inavyofanya kazi:\n\n1️⃣ Chagua bidhaa unayoitaka\n2️⃣ Fungua mpango wa akiba (bure!)\n3️⃣ Weka pesa kidogo kidogo via M-Pesa\n4️⃣ Ukifika 100% — tunakupelekea bidhaa nyumbani!\n\nHakuna riba. Hakuna mkopo. Fedha yako yako! ✅"],

        // Registration
        ['q'=>['jisajili','register','akaunti','open account','fungua'],
         'a'=>"Kujisajili ni bure na rahisi! Unahitaji:\n\n✓ Nambari ya simu (OTP inatumwa)\n✓ Kitambulisho (NIDA au Leseni)\n✓ Anwani yako (kwa delivery)\n\nDakika 2 tu! Bonyeza 'Jisajili Bure' kwenye ukurasa mkuu. 📱"],

        // Security
        ['q'=>['salama','usalama','fedha zangu','hakika','trust'],
         'a'=>"Fedha yako iko salama kabisa! 🔒\n\n✓ KiliSmart imesajiliwa BRELA Tanzania\n✓ Fedha zinashikiliwa kwenye akaunti ya escrow\n✓ Hatutumii fedha zako kwa biashara nyingine\n✓ HTTPS encryption kwenye mawasiliano yote\n\nTunamheshimu kila mteja!"],

        // Referral
        ['q'=>['referral','alika','marafiki','bonus','zawadi'],
         'a'=>"Alika rafiki yako — nyote wawili mnapata TZS 2,000 bonus! 🎁\n\nJinsi inavyofanya kazi:\n1. Shiriki nambari yako ya kipekee\n2. Rafiki anajisajili ukitumia nambari yako\n3. Akiweka amana ya kwanza — TZS 2,000 kwenye wallet yenu wote wawili!\n\nPata nambari yako kwenye Dashboard → Alika Marafiki"],

        // Plans limit
        ['q'=>['mipango mingapi','plans limit','zaidi ya moja','mpango mpya'],
         'a'=>"Unaweza kuwa na mipango 3 inayofanya kazi kwa wakati mmoja! 🎯\n\nKila mpango ni kwa bidhaa tofauti. Unaweza kuweka pesa kwenye yote kwa wakati mmoja — kupitia M-Pesa.\n\nFungua mpango mpya kwenye 'Angalia Bidhaa' kwenye homepage."],
    ];

    public function respond(string $message, ?User $user = null): array
    {
        $lower   = strtolower(trim($message));
        $tokens  = explode(' ', $lower);

        $bestMatch  = null;
        $bestScore  = 0;

        foreach ($this->knowledgeBase as $entry) {
            $score = 0;
            foreach ($entry['q'] as $keyword) {
                if (str_contains($lower, $keyword)) $score += 2;
                foreach ($tokens as $token) {
                    similar_text($token, $keyword, $pct);
                    if ($pct > 75) $score += 1;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $entry;
            }
        }

        // Personalize if user is logged in
        $answer = $bestMatch ? $bestMatch['a'] : null;
        if ($answer && $user) {
            $name = explode(' ', $user->full_name)[0];
            $answer = "Habari {$name}! " . $answer;
        }

        $confidence = $bestScore > 0 ? min(1.0, $bestScore / 6) : 0;
        $escalate   = $confidence < 0.4;

        return [
            'answer'     => $answer ?? "Samahani, sijaelewa swali lako vizuri. Wasiliana nasi moja kwa moja:\n📱 WhatsApp: +255 700 000 000\n⏰ Jumatatu–Jumapili · 7:00–21:00",
            'confidence' => $confidence,
            'escalate'   => $escalate,
            'suggested_actions' => $this->suggestedActions($lower),
        ];
    }

    protected function suggestedActions(string $message): array
    {
        $actions = [];
        if (str_contains($message, 'toa') || str_contains($message, 'pesa'))
            $actions[] = ['label' => 'Toa Pesa', 'route' => '/dashboard#wallet'];
        if (str_contains($message, 'bidhaa') || str_contains($message, 'nunua'))
            $actions[] = ['label' => 'Angalia Bidhaa', 'route' => '/'];
        if (str_contains($message, 'mpango') || str_contains($message, 'anza'))
            $actions[] = ['label' => 'Mipango Yangu', 'route' => '/dashboard#plans'];
        $actions[] = ['label' => 'WhatsApp Support', 'route' => 'whatsapp://send?phone=255700000000'];
        return array_slice($actions, 0, 3);
    }

    // ── FUTURE: Use Claude API for more nuanced responses ──────
    // public function respondWithClaude(string $message, User $user): array
    // {
    //     $context = "You are KiliBot, a friendly customer support AI for KiliSmart Tanzania.
    //         KiliSmart is a savings-first ecommerce platform where customers save small amounts
    //         via M-Pesa to buy products. Always respond in Swahili. Be warm and helpful.
    //         Customer: {$user->full_name}, Balance: TZS {$user->wallet->balance}";
    //
    //     $response = Http::withHeaders(['x-api-key' => config('ai.anthropic_key')])
    //         ->post('https://api.anthropic.com/v1/messages', [
    //             'model' => 'claude-haiku-4-5-20251001',
    //             'max_tokens' => 300,
    //             'system' => $context,
    //             'messages' => [['role' => 'user', 'content' => $message]]
    //         ]);
    //
    //     return ['answer' => $response->json('content.0.text'), 'confidence' => 0.95];
    // }
}
