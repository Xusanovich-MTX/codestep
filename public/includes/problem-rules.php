<?php
/**
 * Server-owned output rules. Do not expose the complete returned rule to clients:
 * requirements are public, while expected values and tests belong to the judge.
 * Structured exercises check only the displayed format, never factual accuracy.
 */
function problemRule(int $topicId, string $number): ?array
{
    static $catalog = null;
    if ($catalog === null) {
        $catalog = [14 => [], 15 => []];
        $exact = static function (string $expected, array $requirements, string $stdin = ''): array {
            return [
                'kind' => 'exact',
                'requirements' => $requirements,
                'expected' => $expected,
                'whitespace' => 'trim',
                'tests' => [['stdin' => $stdin, 'expected' => $expected]],
            ];
        };
        $field = static function (string $label, array $aliases = []): array {
            return ['label' => $label, 'aliases' => array_values(array_unique(array_merge([$label], $aliases)))];
        };
        $structured = static function (array $fields, string $layout = 'fields', int $minEntries = 0): array {
            $labels = implode(', ', array_column($fields, 'label'));
            $requirements = $layout === 'table'
                ? [
                    'Birinchi qatorda ustun nomlarini yozing: ' . $labels . '.',
                    'Sarlavhadan keyin kamida ' . $minEntries . ' ta ma’lumot qatori chiqaring. Har bir ustunda qiymat bo‘lsin.',
                    'Ustunlarni | belgisi bilan ajrating.',
                ]
                : [
                    'Quyidagi maydonlarni alohida qatorlarda yozing: ' . $labels . '.',
                    'Har bir qatorda «Maydon nomi: qiymat» ko‘rinishidan foydalaning. Qiymatni bo‘sh qoldirmang.',
                ];
            $requirements[] = 'Bu tekshiruv faqat chiqish tuzilishini baholaydi; ma’lumotlarning mazmuni va faktlarning to‘g‘riligi avtomatik tasdiqlanmaydi.';
            $rule = [
                'kind' => 'structured',
                'scope' => 'structure_only',
                'layout' => $layout,
                'requirements' => $requirements,
                'fields' => $fields,
                'min_rows' => $layout === 'table' ? $minEntries + 1 : count($fields),
                'tests' => [['stdin' => '']],
            ];
            if ($layout === 'table') {
                $rule['min_entries'] = $minEntries;
                $rule['delimiter'] = '|';
            }
            return $rule;
        };

        $binaryInputs = [
            [1254, 16], [2541, 16], [3675, 15], [7458, 17], [7654, 18],
            [-7654, 18], [-1025, 16], [-5232, 16], [-5879, 14], [-7895, 18],
        ];
        foreach ($binaryInputs as $index => [$value, $width]) {
            $expected = str_pad(decbin($value < 0 ? (1 << $width) + $value : $value), $width, '0', STR_PAD_LEFT);
            $requirements = [
                'Natijada faqat ' . $width . ' ta bitdan iborat ikkilik yozuvni bitta qatorda chiqaring.',
                'Boshidagi nollarni saqlang. Natijaga sarlavha, izoh yoki bo‘sh joy qo‘shmang.',
            ];
            if ($value < 0) {
                $requirements[] = 'Manfiy son uchun ' . $width . ' bitli qo‘shimcha kod (two’s complement) usulidan foydalaning.';
            }
            if ($index === 0) {
                $requirements[] = 'O‘tkazish algoritmining izohini kodga kommentariya sifatida yozishingiz mumkin; avtomatik tekshiruv yakuniy ikkilik natijani baholaydi.';
            }
            $rule = $exact($expected, $requirements, $value . ' ' . $width . "\n");
            $rule['value'] = $value;
            $rule['width'] = $width;
            $rule['input_hint'] = $value . ' ' . $width;
            $catalog[14]['1.1.' . ($index + 1)] = $rule;
        }

        $encodedNumbers = ['101111111001', '10111111100101', '101111110111001', '10110011111101', '100011111111011'];
        for ($exercise = 11; $exercise <= 20; $exercise++) {
            $binary = $encodedNumbers[($exercise - 11) % count($encodedNumbers)];
            $width = strlen($binary);
            $expected = (string) (bindec($binary) - (1 << $width));
            $rule = $exact($expected, [
                'Berilgan ' . $width . ' bitli ikkilik yozuvni qo‘shimcha kod (two’s complement) sifatida talqin qiling.',
                'Natijada faqat manfiy o‘nlik butun sonni bitta qatorda chiqaring. Sarlavha yoki izoh qo‘shmang.',
            ], $binary . "\n");
            $rule['binary'] = $binary;
            $rule['width'] = $width;
            $rule['input_hint'] = $binary;
            $catalog[14]['1.1.' . $exercise] = $rule;
        }

        $catalog[15]['1.1.21'] = [
            'kind' => 'repeat',
            'requirements' => ['«Dasturlash asoslari» matnini aynan 5 marta bitta qatorda chiqaring.', 'Takrorlar orasida bo‘sh joy bo‘lishi mumkin. Boshqa matn chiqarmang.'],
            'text' => 'Dasturlash asoslari',
            'count' => 5,
            'same_line' => true,
            'tests' => [['stdin' => '']],
        ];
        $catalog[15]['1.1.22'] = [
            'kind' => 'numeric',
            'requirements' => ['Faqat π sonining qiymatini, kamida yuzdan bir aniqlikda chiqaring.', 'Kasr qismidan oldin nuqta yozing. Xatolik 0.005 dan oshmasin.'],
            'expected' => '3.141592653589793',
            'tolerance' => 0.005,
            'tests' => [['stdin' => '', 'expected' => '3.141592653589793']],
        ];
        $catalog[15]['1.1.23'] = $exact("50\n10", ['Birinchi qatorda 50, ikkinchi qatorda 10 sonini chiqaring.', 'Jami 2 ta qator bo‘lsin. Sarlavha yoki boshqa matn qo‘shmang.']);
        $numericLines = [
            'kind' => 'numeric_lines',
            'requirements' => ['Aynan 4 ta son chiqaring, har biri alohida qatorda bo‘lsin.', 'Har bir qatorda faqat bitta son yozing. Sarlavha yoki boshqa matn qo‘shmang.'],
            'count' => 4,
            'tests' => [['stdin' => '']],
        ];
        $catalog[15]['1.1.24'] = $numericLines;
        $catalog[15]['1.1.25'] = [
            'kind' => 'name_layout',
            'scope' => 'structure_only',
            'requirements' => [
                'Birinchi qatorda ism va familiyani bitta bo‘sh joy bilan yonma-yon yozing.',
                'Ikkinchi qatorda o‘sha ismni, uchinchi qatorda o‘sha familiyani alohida yozing.',
                'Jami 3 ta qator bo‘lsin. Tekshiruv uchun ism va familiyaning har birini bitta so‘z qilib yozing.',
                'Tekshiruv joylashuv va takrorlangan matnni baholaydi; ismning haqiqiyligi tekshirilmaydi.',
            ],
            'tests' => [['stdin' => '']],
        ];
        $catalog[15]['1.1.26'] = $structured([
            $field('Ism', ['Name']),
            $field('Familiya', ['Surname']),
            $field('Tug‘ilgan yil', ["Tug'ilgan yil", 'Tugilgan yil', 'Tug‘ilgan yili', 'Tugilgan yili']),
            $field('Yosh', ['Yoshi']),
            $field('Manzil', ['Yashash manzili']),
            $field('Telefon raqam', ['Telefon raqami', 'Telefon']),
        ]);
        $catalog[15]['1.1.27'] = $structured([
            $field('Davlat', ['Davlat nomi', 'Mamlakat']),
            $field('Poytaxt', ['Poytaxti']),
            $field('Aholi', ['Aholisi', 'Aholi soni']),
            $field('Millat', ['Millati']),
            $field('Pul birligi', ['Valyuta']),
        ]);
        $catalog[15]['1.1.28'] = $structured([
            $field('Noutbuk', ['Noutbuk modeli', 'Model', 'Modeli']),
            $field('Protsessor', ['CPU']),
            $field('Tezkor xotira', ['RAM']),
        ]);
        $catalog[15]['1.1.29'] = $structured([
            $field('Shahar', ['Shahar nomi']),
            $field('Davlat', ['Davlat nomi', 'Mamlakat']),
            $field('Aholi', ['Aholisi', 'Aholi soni']),
        ]);
        $catalog[15]['1.1.30'] = $structured([
            $field('Kitob nomi', ['Kitob', 'Nomi']),
            $field('Muallif', ['Muallifi', 'Yozuvchi']),
            $field('Janr', ['Janri']),
        ]);
        $catalog[15]['1.1.31'] = $structured([
            $field('Telefon modeli', ['Telefon', 'Model', 'Modeli']),
            $field('Xotira', ['Xotirasi', 'Ichki xotira']),
            $field('Operatsion tizim', ['OS']),
        ]);
        $catalog[15]['1.1.32'] = $structured([
            $field('O‘qish joyi', ["O'qish joyi", 'Oqish joyi', 'Muassasa', 'Muassasa nomi', 'Universitet', 'Maktab']),
            $field('Manzil', ['Manzili']),
            $field('Yo‘nalish yoki sinf', ["Yo'nalish yoki sinf", 'Yonalish yoki sinf', 'Yo‘nalish', "Yo'nalish", 'Yonalish', 'Sinf']),
        ]);
        $catalog[15]['1.1.33'] = $structured([
            $field('Ta’lim muassasasi', ["Ta'lim muassasasi", 'Talim muassasasi', 'Muassasa', 'Muassasa nomi', 'Maktab']),
            $field('Manzil', ['Manzili']),
            $field('Bitirgan yil', ['Bitirgan yili', 'Tugatgan yil', 'Tugatgan yili']),
        ]);
        $catalog[15]['1.1.34'] = $structured([
            $field('Tuman', ['Tuman nomi']),
            $field('Viloyat', ['Viloyat nomi', 'Hudud']),
            $field('Aholi', ['Aholisi', 'Aholi soni']),
        ]);
        $catalog[15]['1.1.35'] = $structured([
            $field('Qishloq', ['Qishloq nomi']),
            $field('Tuman', ['Tuman nomi']),
            $field('Aholi', ['Aholisi', 'Aholi soni']),
        ]);
        $catalog[15]['1.1.36'] = $structured([
            $field('Guruh', ['Guruh nomi', 'Guruh raqami']),
            $field('Yo‘nalish', ["Yo'nalish", 'Yonalish']),
            $field('Talabalar soni', ['Talaba soni', 'Guruhdagi talabalar soni']),
        ]);
        $catalog[15]['1.1.37'] = [
            'kind' => 'poem',
            'scope' => 'structure_only',
            'requirements' => [
                'She’rning aynan 4 ta satrini alohida qatorlarda chiqaring.',
                'Beshinchi qatorda «Muallif: ism-familiya» ko‘rinishida muallifni yozing.',
                'Tekshiruv faqat 4 satr va muallif qatori borligini baholaydi; she’r mazmuni va muallifning to‘g‘riligi avtomatik tasdiqlanmaydi.',
            ],
            'line_count' => 4,
            'author_aliases' => ['Muallif', 'Shoir'],
            'tests' => [['stdin' => '']],
        ];
        $catalog[15]['1.1.38'] = $structured([
            $field('Fakultet', ['Fakultet nomi']),
            $field('Universitet', ['OTM', 'Institut', 'Oliy ta’lim muassasasi']),
            $field('Dekan', ['Dekani']),
        ]);
        $catalog[15]['1.1.39'] = $structured([
            $field('Yo‘nalish', ["Yo'nalish", 'Yonalish', 'Yo‘nalish nomi']),
            $field('Fakultet', ['Fakultet nomi']),
            $field('Ta’lim muddati', ["Ta'lim muddati", 'Talim muddati', 'O‘qish muddati']),
        ]);
        $catalog[15]['1.1.40'] = $structured([
            $field('Bayram', ['Bayram nomi', 'Milliy bayram']),
        ], 'table', 2);
        $catalog[15]['1.1.41'] = $structured([
            $field('Yo‘nalish', ["Yo'nalish", 'Yonalish', 'Yo‘nalish nomi']),
            $field('Guruh', ['Guruh nomi', 'Guruh raqami']),
        ], 'table', 2);
        $catalog[15]['1.1.42'] = $structured([
            $field('Jamoa', ['Jamoa nomi', 'Klub']),
            $field('O‘yinlar', ["O'yinlar", 'Oyinlar', 'O‘yinlar soni', 'Oyinlar soni']),
            $field('Ochko', ['Ochkolar', 'Ball']),
        ], 'table', 2);
        $catalog[15]['1.1.43'] = $structured([
            $field('Sinf', ['Sinf nomi', 'Sinf raqami']),
            $field('O‘quvchilar soni', ["O'quvchilar soni", 'Oquvchilar soni', 'O‘quvchi soni', 'Oquvchi soni']),
        ], 'table', 2);
        $catalog[15]['1.1.44'] = $structured([
            $field('Muallif', ['Muallifi', 'Yozuvchi']),
            $field('Kitob nomi', ['Kitob', 'Asar', 'Asar nomi']),
        ], 'table', 2);
        $catalog[15]['1.1.45'] = $structured([
            $field('Fan', ['Fan nomi']),
            $field('O‘qituvchi', ["O'qituvchi", 'Oqituvchi', 'Ustoz']),
        ], 'table', 2);
        $catalog[15]['1.1.46'] = $structured([
            $field('Kun', ['Hafta kuni']),
            $field('Vaqt', ['Soat', 'Dars vaqti']),
            $field('Fan', ['Fan nomi', 'Dars']),
        ], 'table', 2);
        $catalog[15]['1.1.47'] = $structured([
            $field('Ism', ['Talaba ismi']),
            $field('Familiya', ['Talaba familiyasi']),
        ], 'table', 2);
        $catalog[15]['1.1.48'] = $exact('3.14', ['π sonini yaxlitlab, nuqtadan keyin aynan 2 ta raqam bilan chiqaring.', 'Faqat bitta son chiqaring. Sarlavha yoki izoh qo‘shmang.']);
        $catalog[15]['1.1.49'] = $exact('2.7', ['e sonini yaxlitlab, nuqtadan keyin aynan 1 ta raqam bilan chiqaring.', 'Faqat bitta son chiqaring. Sarlavha yoki izoh qo‘shmang.']);
        $echoTests = [
            ['stdin' => "7\n", 'expected' => '7'],
            ['stdin' => "0\n", 'expected' => '0'],
            ['stdin' => "-125\n", 'expected' => '-125'],
        ];
        $echoRule = [
            'kind' => 'echo',
            'requirements' => ['Kiritish (stdin) maydonidagi sonni o‘qib, aynan o‘sha sonni chiqaring.', 'Dastur bir nechta kiritish qiymati bilan tekshiriladi. Kiritishga taklif yoki boshqa matn chiqarmang.'],
            'tests' => $echoTests,
            'input_hint' => '7',
        ];
        $catalog[15]['1.1.50'] = $echoRule;
        $catalog[15]['1.1.51'] = [
            'kind' => 'echo',
            'requirements' => [
                'Kiritish (stdin) maydonidagi sonni o‘qing.',
                'Avval «Siz raqam kiritdingiz» xabarini, keyin o‘qilgan sonni chiqaring. Ular orasida bo‘sh joy yoki yangi qator bo‘lishi mumkin.',
                'Dastur bir nechta kiritish qiymati bilan tekshiriladi. Boshqa matn chiqarmang.',
            ],
            'prefix' => 'Siz raqam kiritdingiz',
            'tests' => array_map(static fn(array $test): array => [
                'stdin' => $test['stdin'],
                'expected' => 'Siz raqam kiritdingiz ' . $test['expected'],
            ], $echoTests),
            'input_hint' => '7',
        ];
        $catalog[15]['1.1.52'] = $echoRule;
        $catalog[15]['1.1.53'] = $exact('1 13 49', ['1, 13 va 49 sonlarini shu tartibda bitta qatorda chiqaring.', 'Sonlar orasida aynan bitta bo‘sh joy bo‘lsin. Boshqa matn qo‘shmang.']);
        $catalog[15]['1.1.53']['whitespace'] = 'single_spaces';
        $catalog[15]['1.1.54'] = $numericLines;
        $catalog[15]['1.1.55'] = $exact("5 10\n100 t\nx 25\n7 sm\n1949 v\nx y", [
            'Masalada berilgan 6 ta ma’lumot qatorini shu tartibda chiqaring.',
            'Har bir qatordagi ikki qiymat orasida bitta bo‘sh joy bo‘lsin.',
            'a), b), c), d), e), f) belgilarini yozish ixtiyoriy. Boshqa sarlavha yoki izoh qo‘shmang.',
        ]);
        $catalog[15]['1.1.55']['optional_line_labels'] = true;
    }

    return $catalog[$topicId][$number] ?? null;
}
