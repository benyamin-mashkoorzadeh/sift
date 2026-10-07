<?php

return [
    'guest' => [
        'name' => 'Guest Demo',
        'email' => 'demo-guest@sift.internal',
    ],
    'workspace' => [
        'name' => 'Lumenfield Supply',
        'slug' => 'lumenfield-demo',
    ],
    'documents' => [
        [
            'filename' => 'Returns, Refunds & Order Changes.pdf',
            'asset' => 'returns-refunds-order-changes.pdf',
            'storage_path' => 'demo/lumenfield-demo/returns-refunds-order-changes.pdf',
            'sha256' => '73995b09cc07c90a55de5a9abedd5be74a70cef10037cfab10112271051ec8b2',
            'page_count' => 2,
        ],
        [
            'filename' => 'Shipping & Delivery.pdf',
            'asset' => 'shipping-delivery.pdf',
            'storage_path' => 'demo/lumenfield-demo/shipping-delivery.pdf',
            'sha256' => 'b4a8725829244132c7e8463510e14f28bb9d4a37e16cd73799877697b6a74a1d',
            'page_count' => 2,
        ],
        [
            'filename' => 'Warranty & Support.pdf',
            'asset' => 'warranty-support.pdf',
            'storage_path' => 'demo/lumenfield-demo/warranty-support.pdf',
            'sha256' => 'ac832999c0941dd46a41b0534fac5485ad7308fdc0e7d936ae9961b4b0065b54',
            'page_count' => 2,
        ],
    ],
    'reviews' => [
        [
            'question' => 'Do you match competitor prices?',
            'status' => 'pending',
            'resolution' => null,
        ],
        [
            'question' => 'Can I transfer the warranty to another owner?',
            'status' => 'resolved',
            'resolution' => 'The warranty applies only to the original purchaser and is not transferable.',
        ],
    ],
    'interactions' => [
        [
            'question' => 'How long do I have to return an unused product?',
            'status' => 'answered',
            'answer' => 'Unused products may be returned within 30 days of delivery, provided they are in resalable condition with their original accessories and proof of purchase.',
            'citation' => [
                'storage_path' => 'demo/lumenfield-demo/returns-refunds-order-changes.pdf',
                'page_number' => 1,
                'phrase' => 'Unused products may be returned within 30 days of delivery.',
            ],
        ],
        [
            'question' => 'Can I cancel an order after placing it?',
            'status' => 'answered',
            'answer' => 'An order may be cancelled within 30 minutes of placement if fulfillment has not started. After that, Support can try to stop shipment but cannot guarantee cancellation.',
            'citation' => [
                'storage_path' => 'demo/lumenfield-demo/returns-refunds-order-changes.pdf',
                'page_number' => 2,
                'phrase' => 'An order may be cancelled within 30 minutes of placement',
            ],
        ],
        [
            'question' => 'What should I do if the wrong product arrives?',
            'status' => 'answered',
            'answer' => 'Report an incorrect product within 7 days of delivery and include the order number, a short description, and clear photos of the product and packaging.',
            'citation' => [
                'storage_path' => 'demo/lumenfield-demo/shipping-delivery.pdf',
                'page_number' => 2,
                'phrase' => 'A damaged or incorrect product must be reported within 7 days of delivery.',
            ],
        ],
        [
            'question' => 'How long does the product warranty last?',
            'status' => 'answered',
            'answer' => 'Lumenfield Supply products include a 12-month limited warranty beginning on the original delivery date.',
            'citation' => [
                'storage_path' => 'demo/lumenfield-demo/warranty-support.pdf',
                'page_number' => 1,
                'phrase' => '12-month limited warranty',
            ],
        ],
        [
            'question' => 'Do you match competitor prices?',
            'status' => 'needs_review',
            'answer' => 'I could not find enough information in the knowledge base to answer that reliably. This question needs human review.',
        ],
        [
            'question' => 'Can I transfer the warranty to another owner?',
            'status' => 'needs_review',
            'answer' => 'I could not find enough information in the knowledge base to answer that reliably. This question needs human review.',
        ],
    ],
];
