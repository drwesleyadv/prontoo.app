<?php
$brLandingStructuredData = [
    "@context" => "https://schema.org",
    "@graph" => [
        [
            "@type" => "Organization",
            "@id" => br_landing_url("#organization"),
            "name" => "Prontoo",
            "url" => br_landing_url(),
            "logo" => [
                "@type" => "ImageObject",
                "url" => $brLandingLogo,
            ],
            "description" =>
                "Sistema online para organização operacional de consultórios e clínicas.",
        ],
        [
            "@type" => "WebSite",
            "@id" => br_landing_url("#website"),
            "url" => br_landing_url(),
            "name" => "Prontoo",
            "publisher" => ["@id" => br_landing_url("#organization")],
            "inLanguage" => "pt-BR",
        ],
        [
            "@type" => "WebPage",
            "@id" => $brLandingCanonical . "#webpage",
            "url" => $brLandingCanonical,
            "name" => $brLandingTitle,
            "description" => $brLandingDescription,
            "isPartOf" => ["@id" => br_landing_url("#website")],
            "about" => ["@id" => $brLandingCanonical . "#software"],
            "primaryImageOfPage" => [
                "@type" => "ImageObject",
                "url" => $brLandingLogo,
            ],
            "inLanguage" => "pt-BR",
            "dateModified" => "2026-07-14",
        ],
        [
            "@type" => "SoftwareApplication",
            "@id" => $brLandingCanonical . "#software",
            "name" => "Prontoo",
            "url" => $brLandingCanonical,
            "image" => $brLandingLogo,
            "description" => $brLandingDescription,
            "applicationCategory" => "BusinessApplication",
            "operatingSystem" => "Web",
            "audience" => [
                "@type" => "Audience",
                "audienceType" => "Consultórios, clínicas e equipes de saúde",
            ],
            "featureList" => [
                "Ficha do paciente centralizada",
                "Agenda online",
                "Documentos e modelos do consultório",
                "Tarefas e avisos internos",
                "Permissões por função",
                "Registro de atividades",
                "Jornada do paciente",
            ],
            "offers" => [
                "@type" => "Offer",
                "url" => $brLandingSignup,
                "category" => "Software de gestão clínica",
                "availability" => "https://schema.org/InStock",
                "description" => "Período gratuito de 30 dias para novos consultórios.",
            ],
        ],
        [
            "@type" => "FAQPage",
            "@id" => $brLandingCanonical . "#faq",
            "mainEntity" => array_map(
                static fn(array $item): array => [
                    "@type" => "Question",
                    "name" => $item["q"],
                    "acceptedAnswer" => [
                        "@type" => "Answer",
                        "text" => $item["a"],
                    ],
                ],
                $brLandingFaq,
            ),
        ],
    ],
];

