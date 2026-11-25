<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SmartOra AI — منصة الرد الآلي الذكي للواتساب وانستقرام</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f8fafc;
            --surface: #ffffff;
            --surface-soft: #f1f5f9;
            --text: #0f172a;
            --muted: #475569;
            --subtle: #64748b;
            --border: #e2e8f0;
            --emerald: #059669;
            --emerald-dark: #047857;
            --emerald-soft: #ecfdf5;
            --sky: #0284c7;
            --sky-soft: #f0f9ff;
            --amber-soft: #fffbeb;
            --amber-text: #b45309;
            --radius: 1rem;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
            overflow-x: clip;
        }

        body {
            font-family: 'Tajawal', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.65;
            overflow-x: clip;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding-inline: 1.25rem;
        }

        /* Navbar */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
        }

        .nav-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            height: 70px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            font-weight: 800;
        }

        .brand-icon {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: linear-gradient(135deg, #10b981, #0d9488);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 14px -4px rgba(16, 185, 129, 0.45);
            flex-shrink: 0;
        }

        .brand-title {
            font-size: 1.15rem;
            line-height: 1.2;
        }

        .brand-title span {
            color: var(--emerald);
        }

        .brand-sub {
            font-size: 0.72rem;
            color: var(--subtle);
            font-weight: 500;
        }

        .nav-links {
            display: none;
            align-items: center;
            gap: 1.5rem;
            font-size: 0.92rem;
            font-weight: 600;
            color: var(--muted);
        }

        .nav-links a:hover {
            color: var(--emerald);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            padding: 0.62rem 1.15rem;
            border-radius: 0.75rem;
            font-weight: 700;
            font-size: 0.9rem;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.18s ease;
            font-family: inherit;
        }

        .btn-primary {
            background: var(--emerald);
            color: #fff;
            box-shadow: 0 8px 18px -6px rgba(5, 150, 105, 0.45);
        }

        .btn-primary:hover {
            background: var(--emerald-dark);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #fff;
            color: var(--text);
            border-color: var(--border);
        }

        .btn-secondary:hover {
            background: var(--surface-soft);
            border-color: #cbd5e1;
        }

        .btn-sky {
            background: var(--sky);
            color: #fff;
        }

        .btn-sky:hover {
            background: #0369a1;
        }

        .btn-lg {
            padding: 0.85rem 1.5rem;
            font-size: 1rem;
            border-radius: 0.85rem;
        }

        /* Hero */
        .hero {
            padding: 3.5rem 0 3rem;
            background:
                radial-gradient(circle at 85% 15%, rgba(16, 185, 129, 0.12), transparent 36%),
                radial-gradient(circle at 12% 25%, rgba(2, 132, 199, 0.10), transparent 38%);
        }

        .hero-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2.5rem;
            align-items: center;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.9rem;
            border-radius: 999px;
            background: var(--emerald-soft);
            color: var(--emerald-dark);
            border: 1px solid #a7f3d0;
            font-size: 0.82rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .hero-title {
            font-size: clamp(1.9rem, 3.6vw, 2.85rem);
            font-weight: 900;
            line-height: 1.25;
            color: var(--text);
            margin-bottom: 1rem;
        }

        .hero-title span {
            color: var(--emerald);
        }

        .hero-desc {
            font-size: 1.05rem;
            color: var(--muted);
            margin-bottom: 1.65rem;
            max-width: 580px;
        }

        .hero-ctas {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .hero-trust {
            display: flex;
            flex-wrap: wrap;
            gap: 1.25rem;
            font-size: 0.86rem;
            color: var(--subtle);
            font-weight: 600;
        }

        .hero-trust span {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        /* Mockup Chat Card */
        .chat-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 1.25rem;
            box-shadow: 0 22px 45px -20px rgba(15, 23, 42, 0.18);
            overflow: hidden;
        }

        .chat-head {
            background: #065f46;
            color: #fff;
            padding: 0.9rem 1.15rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chat-head-user {
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        .chat-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #10b981;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
        }

        .chat-body {
            background: #efeae2;
            padding: 1.15rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .bubble {
            max-width: 86%;
            padding: 0.65rem 0.9rem;
            border-radius: 0.9rem;
            font-size: 0.88rem;
            line-height: 1.55;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
        }

        .bubble-user {
            align-self: flex-start;
            background: #d9fdd3;
            color: #0f172a;
            border-top-right-radius: 0.2rem;
        }

        .bubble-bot {
            align-self: flex-end;
            background: #ffffff;
            color: #0f172a;
            border-top-left-radius: 0.2rem;
        }

        .bubble-meta {
            font-size: 0.7rem;
            color: var(--emerald);
            font-weight: 700;
            margin-bottom: 0.2rem;
        }

        /* Section Common */
        .section {
            padding: 3.5rem 0;
        }

        .section-white {
            background: #fff;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .section-head {
            text-align: center;
            max-width: 680px;
            margin: 0 auto 2.25rem;
        }

        .section-kicker {
            display: inline-block;
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--emerald);
            background: var(--emerald-soft);
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            margin-bottom: 0.6rem;
        }

        .section-title {
            font-size: clamp(1.5rem, 2.5vw, 2.05rem);
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .section-subtitle {
            color: var(--muted);
            font-size: 0.98rem;
        }

        /* Features */
        .features-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.15rem;
        }

        .feature-card {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.35rem;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        .feature-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px -12px rgba(15, 23, 42, 0.12);
        }

        .feature-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.85rem;
            font-size: 1.25rem;
        }

        .feature-card h3 {
            font-size: 1.02rem;
            font-weight: 800;
            margin-bottom: 0.35rem;
        }

        .feature-card p {
            font-size: 0.88rem;
            color: var(--muted);
        }

        /* How it works */
        .steps-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.15rem;
        }

        .step-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.35rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .step-num {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--emerald);
            color: #fff;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        /* Pricing */
        .pricing-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.15rem;
            align-items: stretch;
        }

        .plan-card {
            background: #fff;
            border: 1.5px solid var(--border);
            border-radius: 1.15rem;
            padding: 1.45rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        .plan-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 18px 34px -18px rgba(15, 23, 42, 0.16);
        }

        .plan-card.highlighted {
            border-color: var(--emerald);
            box-shadow: 0 20px 38px -18px rgba(5, 150, 105, 0.28);
        }

        .plan-badge {
            display: inline-block;
            font-size: 0.74rem;
            font-weight: 800;
            padding: 0.24rem 0.68rem;
            border-radius: 999px;
            margin-bottom: 0.75rem;
            background: var(--surface-soft);
            color: var(--muted);
        }

        .plan-card.highlighted .plan-badge {
            background: var(--emerald);
            color: #fff;
        }

        .plan-card[data-plan="free_trial"] .plan-badge {
            background: var(--sky-soft);
            color: var(--sky);
        }

        .plan-card[data-plan="yearly"] .plan-badge {
            background: var(--amber-soft);
            color: var(--amber-text);
        }

        .plan-name {
            font-size: 1.15rem;
            font-weight: 800;
        }

        .plan-duration {
            font-size: 0.82rem;
            color: var(--subtle);
            margin-bottom: 0.85rem;
        }

        .plan-price-row {
            display: flex;
            align-items: baseline;
            gap: 0.45rem;
            margin-bottom: 0.2rem;
        }

        .plan-price {
            font-size: 2rem;
            font-weight: 900;
            color: var(--text);
            line-height: 1.1;
        }

        .plan-old-price {
            font-size: 0.92rem;
            color: #94a3b8;
            text-decoration: line-through;
            font-weight: 700;
        }

        .plan-per {
            font-size: 0.84rem;
            font-weight: 700;
            color: var(--emerald-dark);
            margin-bottom: 0.35rem;
        }

        .plan-saving {
            font-size: 0.78rem;
            color: var(--subtle);
            margin-bottom: 1rem;
            padding-bottom: 0.9rem;
            border-bottom: 1px solid var(--border);
        }

        .plan-features {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
            margin-bottom: 1.35rem;
            font-size: 0.86rem;
            color: var(--muted);
        }

        .plan-features li {
            display: flex;
            align-items: flex-start;
            gap: 0.45rem;
        }

        .plan-features li::before {
            content: '✓';
            color: var(--emerald);
            font-weight: 900;
            flex-shrink: 0;
        }

        .negotiate-bar {
            margin-top: 1.5rem;
            padding: 1.15rem 1.4rem;
            border-radius: var(--radius);
            background: linear-gradient(135deg, #ecfdf5, #f0f9ff);
            border: 1px solid #bae6fd;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        /* Registration */
        .register-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.75rem;
            align-items: start;
        }

        .register-info-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 1.15rem;
            padding: 1.5rem;
        }

        .register-form-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 1.15rem;
            padding: 1.65rem;
            box-shadow: 0 18px 36px -22px rgba(15, 23, 42, 0.16);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.95rem;
        }

        .form-group label {
            display: block;
            font-size: 0.84rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
            color: var(--text);
        }

        .form-control {
            width: 100%;
            padding: 0.65rem 0.85rem;
            border-radius: 0.7rem;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            font-family: inherit;
            font-size: 0.92rem;
            color: var(--text);
            transition: border-color 0.15s ease, background 0.15s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--emerald);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12);
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 0.85rem;
            padding: 0.9rem 1.1rem;
            margin-bottom: 1.1rem;
            font-size: 0.86rem;
        }

        .alert-error ul {
            padding-right: 1.1rem;
            list-style: disc;
        }

        .success-box {
            background: #ecfdf5;
            border: 1.5px solid #6ee7b7;
            border-radius: 1.15rem;
            padding: 1.75rem;
            text-align: center;
        }

        .footer {
            background: #0f172a;
            color: #cbd5e1;
            padding: 2rem 0;
            font-size: 0.86rem;
        }

        .footer-inner {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .footer-links {
            display: flex;
            flex-wrap: wrap;
            gap: 1.25rem;
        }

        .footer-links a:hover {
            color: #fff;
        }

        @media (min-width: 768px) {
            .nav-links {
                display: flex;
            }
            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .steps-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .pricing-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .form-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .form-full {
                grid-column: span 2;
            }
        }

        @media (min-width: 1024px) {
            .hero-grid {
                grid-template-columns: 1.15fr 0.85fr;
            }
            .features-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .pricing-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            .register-grid {
                grid-template-columns: 0.9fr 1.1fr;
            }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <header class="navbar">
        <div class="container nav-inner">
            <a href="{{ url('/') }}" class="brand">
                <div class="brand-icon">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <div>
                    <div class="brand-title">SmartOra <span>AI</span></div>
                    <div class="brand-sub">الرد الآلي الذكي للشركات</div>
                </div>
            </a>

            <nav class="nav-links">
                <a href="#features">المميزات</a>
                <a href="#how-it-works">كيف يعمل</a>
                <a href="#pricing">الباقات والأسعار</a>
                <a href="#register">إنشاء حساب</a>
            </nav>

            <div class="nav-actions">
                @auth
                    <a href="{{ auth()->user()->isSuperAdmin() ? url('/admin') : url('/company') }}" class="btn btn-primary">
                        الذهاب إلى لوحة التحكم
                    </a>
                @else
                    <a href="{{ url('/company/login') }}" class="btn btn-secondary">تسجيل الدخول</a>
                    <a href="#register" class="btn btn-primary">ابدأ شهرك المجاني</a>
                @endauth
            </div>
        </div>
    </header>

    <!-- Hero -->
    <section class="hero">
        <div class="container hero-grid">
            <div>
                <div class="hero-badge">⚡ أتمتة ذكية لخدمة العملاء 24/7 على واتساب وانستقرام</div>
                <h1 class="hero-title">
                    حوّل رسائل <span>واتساب وانستقرام</span> إلى مبيعات فورية بالذكاء الاصطناعي
                </h1>
                <p class="hero-desc">
                    ارفع ملفات أسعارك وخدماتك (Excel أو PDF)، وسيقوم مساعد SmartOra AI بالرد الفوري والدقيق على استفسارات عملائك في الواتساب ورسائل الخاص والتعليقات على مدار الساعة.
                </p>

                <div class="hero-ctas">
                    <a href="#register" onclick="selectPlan('free_trial')" class="btn btn-primary btn-lg">
                        🚀 ابدأ شهراً مجاناً الآن
                    </a>
                    <a href="#pricing" class="btn btn-secondary btn-lg">
                        استعرض الباقات والأسعار
                    </a>
                </div>

                <div class="hero-trust">
                    <span>✓ شهر تجريبي مجاني بالكامل</span>
                    <span>✓ ربط واتساب عبر QR أو Meta الرسمي</span>
                    <span>✓ الأسعار قابلة للنقاش</span>
                </div>
            </div>

            <div class="chat-card">
                <div class="chat-head">
                    <div class="chat-head-user">
                        <div class="chat-avatar">S</div>
                        <div>
                            <div style="font-weight:800;font-size:0.95rem;">متجر النور · SmartOra AI</div>
                            <div style="font-size:0.75rem;color:#a7f3d0;">● متصل ويرد تلقائياً من ملف الأسعار</div>
                        </div>
                    </div>
                    <span style="font-size:0.74rem;background:rgba(255,255,255,0.16);padding:0.2rem 0.6rem;border-radius:999px;">واتساب + انستقرام</span>
                </div>
                <div class="chat-body">
                    <div class="bubble bubble-user">
                        السلام عليكم، كم سعر فحمات الفرامل الأمامية لتويوتا وهل أنتم فاتحين الآن؟
                    </div>
                    <div class="bubble bubble-bot">
                        <div class="bubble-meta">⚡ رد فوري من SmartOra AI</div>
                        وعليكم السلام! أهلاً بك في متجر النور.<br>
                        • فحمات فرامل أمامية تويوتا (أصلي): متوفرة بسعر <strong>18,000 ريال</strong>.<br>
                        • أوقات الدوام: من 8 صباحاً حتى 9 مساءً (السبت - الخميس).<br>
                        هل ترغب بتأكيد الطلب أو التحويل لمسؤول المبيعات؟
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section id="features" class="section section-white">
        <div class="container">
            <div class="section-head">
                <span class="section-kicker">ماذا نقدم لمنشأتك؟</span>
                <h2 class="section-title">كل ما تحتاجه لأتمتة الرد على العملاء في مكان واحد</h2>
                <p class="section-subtitle">منصة مصممة خصيصاً للشركات والمتاجر والعيادات لتوفير الوقت ورفع سرعة الاستجابة.</p>
            </div>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon" style="background:#ecfdf5;color:#059669;">💬</div>
                    <h3>ربط واتساب مرن وموثوق</h3>
                    <p>اربط رقم منشأتك خلال ثوانٍ عبر مسح الباركود (QR) أو عبر الربط السحابي الرسمي Meta Cloud API.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon" style="background:#fdf2f8;color:#db2777;">📸</div>
                    <h3>أتمتة انستقرام DM والتعليقات</h3>
                    <p>رد آلي على رسائل الخاص، ردود الستوري، وتحويل التعليقات على المنشورات إلى محادثات خاصة ذكية.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon" style="background:#f0f9ff;color:#0284c7;">📊</div>
                    <h3>قراءة ملفات الأسعار (Excel & PDF)</h3>
                    <p>ارفع كتالوجاتك وقوائم الأسعار بصيغ Excel أو CSV أو PDF ليقرأ منها البوت ويجيب بدقة تامة.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon" style="background:#fefce8;color:#ca8a04;">🏢</div>
                    <h3>بوابة تحكم مستقلة لكل شركة</h3>
                    <p>تعديل بيانات المنشأة، ساعات العمل، رابط الخريطة، رسالة الترحيب، وسجل محادثات العملاء بسهولة.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon" style="background:#f5f3ff;color:#7c3aed;">🧪</div>
                    <h3>محاكي مباشر لتجربة البوت</h3>
                    <p>اختبر إجابات الذكاء الاصطناعي من ملفاتك داخل لوحة التحكم قبل إطلاق الخدمة لعملائك الفعليين.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon" style="background:#fff1f2;color:#e11d48;">🤝</div>
                    <h3>تحويل سلس للدعم البشري</h3>
                    <p>عند الحاجة لتأكيد طلب أو استفسار خاص، يوجّه البوت العميل مباشرة إلى رقم المبيعات المسؤول.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="section">
        <div class="container">
            <div class="section-head">
                <span class="section-kicker">آلية العمل</span>
                <h2 class="section-title">ابدأ في 3 خطوات واضحة</h2>
                <p class="section-subtitle">يتم مراجعة كل طلب تسجيل وتفعيله من إدارة المنصة لضمان جودة التشغيل وتجهيز الحساب.</p>
            </div>

            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-num">1</div>
                    <div>
                        <h3 style="font-weight:800;margin-bottom:0.25rem;">أنشئ حساب منشأتك</h3>
                        <p style="font-size:0.88rem;color:var(--muted);">اختر الباقة المناسبة (أو ابدأ بالشهر المجاني) وأدخل بيانات منشأتك في نموذج التسجيل أدناه.</p>
                    </div>
                </div>

                <div class="step-card">
                    <div class="step-num">2</div>
                    <div>
                        <h3 style="font-weight:800;margin-bottom:0.25rem;">اعتماد وتفعيل الإدارة</h3>
                        <p style="font-size:0.88rem;color:var(--muted);">يصل طلبك فوراً لإدارة المنصة لمراجعته وتفعيل اشتراكك وبوابة الشركة الخاصة بك.</p>
                    </div>
                </div>

                <div class="step-card">
                    <div class="step-num">3</div>
                    <div>
                        <h3 style="font-weight:800;margin-bottom:0.25rem;">ارفع ملفاتك واربط قنواتك</h3>
                        <p style="font-size:0.88rem;color:var(--muted);">سجّل دخولك وارفع ملف الأسعار واربط واتساب أو انستقرام ليبدأ البوت بالرد التلقائي فوراً.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing -->
    <section id="pricing" class="section section-white">
        <div class="container">
            <div class="section-head">
                <span class="section-kicker">الباقات والاشتراكات</span>
                <h2 class="section-title">خطط مرنة تبدأ بشهر مجاني بالكامل</h2>
                <p class="section-subtitle">السعر الأساسي <strong>$13 شهرياً</strong> فقط — مع خصم تلقائي كلما طالت مدة الاشتراك، وجميع الأسعار قابلة للنقاش.</p>
            </div>

            <div class="pricing-grid">
                @foreach($plans as $plan)
                    <div class="plan-card {{ $plan['highlighted'] ? 'highlighted' : '' }}" data-plan="{{ $plan['key'] }}">
                        <div>
                            <span class="plan-badge">{{ $plan['discount_badge'] }}</span>
                            <h3 class="plan-name">{{ $plan['name'] }}</h3>
                            <div class="plan-duration">{{ $plan['duration_label'] }}</div>

                            <div class="plan-price-row">
                                <span class="plan-price">{{ $plan['price_display'] }}</span>
                                @if($plan['monthly_price'] > 0 && $plan['monthly_price'] < $plan['original_monthly_price'])
                                    <span class="plan-old-price">${{ $plan['original_monthly_price'] }}</span>
                                @elseif($plan['monthly_price'] === 0)
                                    <span class="plan-old-price">$13</span>
                                @endif
                            </div>

                            <div class="plan-per">{{ $plan['per_month_note'] }}</div>
                            <div class="plan-saving">{{ $plan['savings_text'] }}</div>

                            <ul class="plan-features">
                                @foreach($plan['features'] as $feature)
                                    <li>{{ $feature }}</li>
                                @endforeach
                            </ul>
                        </div>

                        <a href="#register"
                           onclick="selectPlan('{{ $plan['key'] }}')"
                           class="btn {{ $plan['highlighted'] ? 'btn-primary' : 'btn-secondary' }}"
                           style="width:100%;">
                            {{ $plan['cta'] }}
                        </a>
                    </div>
                @endforeach
            </div>

            <div class="negotiate-bar">
                <div>
                    <div style="font-weight:800;font-size:1.02rem;color:var(--text);">
                        💬 الأسعار قابلة للنقاش والتفاوض حسب احتياج منشأتك
                    </div>
                    <div style="font-size:0.86rem;color:var(--muted);">
                        هل لديك أكثر من فرع أو متطلبات خاصة؟ تواصل معنا مباشرة لمناقشة السعر وتجهيز باقة تناسبك.
                    </div>
                </div>

                <a href="https://wa.me/{{ $salesWhatsapp }}?text={{ urlencode('مرحباً، أرغب في الاستفسار ومناقشة باقات الاشتراك في منصة SmartOra AI.') }}"
                   target="_blank"
                   rel="noopener"
                   class="btn btn-sky">
                    تواصل عبر واتساب لمناقشة السعر
                </a>
            </div>
        </div>
    </section>

    <!-- Registration Section -->
    <section id="register" class="section">
        <div class="container">
            <div class="section-head">
                <span class="section-kicker">إنشاء حساب منشأة</span>
                <h2 class="section-title">سجّل شركتك الآن وابدأ تجربة الرد الذكي</h2>
                <p class="section-subtitle">بعد إرسال الطلب يتم مراجعته وتفعيله من قبل الأدمن لتتمكن من الدخول إلى لوحة التحكم.</p>
            </div>

            @if(session('registration_success'))
                @php($reg = session('registration_success'))
                <div class="success-box">
                    <div style="font-size:2.2rem;margin-bottom:0.5rem;">🎉</div>
                    <h3 style="font-size:1.3rem;font-weight:900;color:#065f46;margin-bottom:0.5rem;">
                        تم استلام طلب تسجيل منشأة "{{ $reg['company_name'] }}" بنجاح!
                    </h3>
                    <p style="font-size:0.95rem;color:#047857;max-width:640px;margin:0 auto 1rem;">
                        حسابك الآن <strong>بانتظار موافقة وتفعيل الإدارة (الأدمن)</strong> على باقة <strong>{{ $reg['plan_label'] }}</strong>.
                        بمجرد التفعيل، يمكنك تسجيل الدخول باستخدام البريد <strong>{{ $reg['email'] }}</strong>.
                    </p>
                    <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:0.75rem;">
                        <a href="https://wa.me/{{ $salesWhatsapp }}?text={{ urlencode('مرحباً، قمت بتسجيل منشأة جديدة (' . $reg['company_name'] . ') على باقة ' . $reg['plan_label'] . ' وأرجو تفعيل الحساب.') }}"
                           target="_blank"
                           rel="noopener"
                           class="btn btn-primary">
                            تواصل لتسريع التفعيل عبر واتساب
                        </a>
                        <a href="{{ url('/company/login') }}" class="btn btn-secondary">
                            صفحة دخول الشركات
                        </a>
                    </div>
                </div>
            @else
                <div class="register-grid">
                    <div class="register-info-card">
                        <h3 style="font-size:1.1rem;font-weight:800;margin-bottom:0.65rem;">ملاحظات هامة عند التسجيل</h3>
                        <ul class="plan-features" style="margin-bottom:1.25rem;">
                            <li>يتم إنشاء الحساب فوراً بحالة <strong>بانتظار التفعيل</strong> لحين مراجعة الإدارة.</li>
                            <li>لن يتم احتساب مدة الاشتراك إلا من لحظة اعتماد وتفعيل الأدمن لحسابك.</li>
                            <li>الباقة التجريبية تمنحك <strong>شهراً مجاناً</strong> كاملاً لاختبار الخدمة.</li>
                            <li>يمكنك تغيير الباقة أو مناقشة السعر مع الإدارة قبل التفعيل.</li>
                        </ul>

                        <div style="padding:1rem;border-radius:0.85rem;background:var(--surface-soft);border:1px solid var(--border);font-size:0.86rem;">
                            <div style="font-weight:800;margin-bottom:0.25rem;">هل لديك استفسار قبل التسجيل؟</div>
                            <div style="color:var(--muted);margin-bottom:0.65rem;">يسعدنا الرد عليك ومناقشة الباقة الأنسب لنشاطك عبر الواتساب.</div>
                            <a href="https://wa.me/{{ $salesWhatsapp }}?text={{ urlencode('مرحباً، أود الاستفسار عن التسجيل في منصة SmartOra AI.') }}"
                               target="_blank"
                               rel="noopener"
                               class="btn btn-secondary"
                               style="width:100%;">
                                تواصل مع المبيعات (واتساب)
                            </a>
                        </div>
                    </div>

                    <div class="register-form-card">
                        @if($errors->any())
                            <div class="alert-error">
                                <div style="font-weight:800;margin-bottom:0.35rem;">يرجى مراجعة الحقول التالية:</div>
                                <ul>
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('company.register.submit') }}">
                            @csrf
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="company_name">اسم الشركة / المتجر *</label>
                                    <input id="company_name" name="company_name" type="text" required class="form-control" value="{{ old('company_name') }}" placeholder="مثال: شركة النور التجارية">
                                </div>

                                <div class="form-group">
                                    <label for="owner_name">اسم المسؤول *</label>
                                    <input id="owner_name" name="owner_name" type="text" required class="form-control" value="{{ old('owner_name') }}" placeholder="مثال: أحمد محمد">
                                </div>

                                <div class="form-group">
                                    <label for="email">البريد الإلكتروني للدخول *</label>
                                    <input id="email" name="email" type="email" required class="form-control" value="{{ old('email') }}" placeholder="info@company.com" dir="ltr">
                                </div>

                                <div class="form-group">
                                    <label for="phone">رقم الهاتف للتواصل *</label>
                                    <input id="phone" name="phone" type="text" required class="form-control" value="{{ old('phone') }}" placeholder="96777xxxxxxx" dir="ltr">
                                </div>

                                <div class="form-group">
                                    <label for="whatsapp_number">رقم واتساب البوت (اختياري)</label>
                                    <input id="whatsapp_number" name="whatsapp_number" type="text" class="form-control" value="{{ old('whatsapp_number') }}" placeholder="يمكنك إضافته لاحقاً بعد التفعيل" dir="ltr">
                                </div>

                                <div class="form-group">
                                    <label for="subscription_plan">الباقة المطلوبة *</label>
                                    @php($currentPlan = old('subscription_plan', $selectedPlan))
                                    <select id="subscription_plan" name="subscription_plan" class="form-control" required>
                                        <option value="free_trial" {{ $currentPlan === 'free_trial' ? 'selected' : '' }}>شهر مجاناً — الباقة التجريبية ($0)</option>
                                        <option value="3_months" {{ $currentPlan === '3_months' ? 'selected' : '' }}>3 أشهر — $13/شهر (الإجمالي $39)</option>
                                        <option value="6_months" {{ $currentPlan === '6_months' ? 'selected' : '' }}>6 أشهر — $12/شهر خصم $1 (الإجمالي $72)</option>
                                        <option value="yearly" {{ $currentPlan === 'yearly' ? 'selected' : '' }}>سنة كاملة — $11/شهر خصم $2 (الإجمالي $132)</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="password">كلمة المرور *</label>
                                    <input id="password" name="password" type="password" required minlength="6" class="form-control" placeholder="6 أحرف على الأقل" dir="ltr">
                                </div>

                                <div class="form-group">
                                    <label for="password_confirmation">تأكيد كلمة المرور *</label>
                                    <input id="password_confirmation" name="password_confirmation" type="password" required minlength="6" class="form-control" placeholder="أعد كتابة كلمة المرور" dir="ltr">
                                </div>

                                <div class="form-group form-full">
                                    <label for="notes">ملاحظات أو طلب نقاش حول السعر (اختياري)</label>
                                    <textarea id="notes" name="notes" rows="2" class="form-control" placeholder="مثال: أرغب في مناقشة باقة متعددة الفروع...">{{ old('notes') }}</textarea>
                                </div>

                                <div class="form-group form-full">
                                    <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">
                                        إرسال طلب التسجيل (بانتظار تفعيل الأدمن)
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container footer-inner">
            <div>
                <strong style="color:#fff;">SmartOra AI</strong> — منصة إدارة الرد الآلي الذكي للشركات
            </div>
            <div class="footer-links">
                <a href="{{ url('/company/login') }}">دخول الشركات</a>
                <a href="{{ url('/admin/login') }}">دخول الإدارة</a>
                <a href="https://wa.me/{{ $salesWhatsapp }}" target="_blank" rel="noopener">واتساب المبيعات</a>
            </div>
        </div>
    </footer>

    <script>
        function selectPlan(planKey) {
            const select = document.getElementById('subscription_plan');
            if (select) {
                select.value = planKey;
            }
        }
        @if(!empty($scrollToRegister) || $errors->any() || session('registration_success'))
            document.addEventListener('DOMContentLoaded', function () {
                const el = document.getElementById('register');
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        @endif
    </script>
</body>
</html>
