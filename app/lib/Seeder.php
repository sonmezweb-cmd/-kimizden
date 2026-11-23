<?php
declare(strict_types=1);

namespace App;

/**
 * Kurulumla gelen gerçek katalog: üç tema, üç paket, ek hizmetler.
 * Demo müşteri, demo sipariş veya uydurma fiyat eklenmez (fiyatları yönetici girer).
 */
final class Seeder
{
    public static function run(): void
    {
        $now = DB::now();
        $themes = [
            ['gul-muhru', 1,
                ['tr' => 'Gül Mührü', 'en' => 'Rose Seal', 'ru' => 'Розовая печать'],
                ['tr' => 'Mürdüm kadife zarf, bakır gül gravürleri ve balmumu mühür. Kaydırdıkça mühür ayrılır, kapak açılır ve iç kâğıt yükselir.',
                 'en' => 'Plum velvet envelope with copper rose engravings and a wax seal. As you scroll, the seal lifts, the flap opens and the letter rises.',
                 'ru' => 'Бархатный конверт цвета сливы с медной гравировкой роз и восковой печатью. При прокрутке печать отделяется, клапан раскрывается и поднимается письмо.'],
                ['sheen' => 'copper', 'font' => 'greatvibes', 'colors' => ['ink' => '#4a1024', 'accent' => '#b87459']]],
            ['zeytin-varak', 2,
                ['tr' => 'Zeytin Varak', 'en' => 'Olive Leaf', 'ru' => 'Оливковая ветвь'],
                ['tr' => 'Akdeniz yeşili kadife, kabartma zeytin dalı ve yaprak damarlarında antik altın yansıması.',
                 'en' => 'Mediterranean green velvet, an embossed olive branch and an antique-gold glint along the leaf veins.',
                 'ru' => 'Средиземноморский зелёный бархат, рельефная оливковая ветвь и отблеск античного золота на прожилках листьев.'],
                ['sheen' => 'gold', 'font' => 'cormorant', 'colors' => ['ink' => '#24382a', 'accent' => '#9c7a36']]],
            ['sedef-kivrimi', 3,
                ['tr' => 'Sedef Kıvrımı', 'en' => 'Pearl Shell', 'ru' => 'Перламутровая раковина'],
                ['tr' => 'İnci beyazı kâğıt, kör kabartma deniz kabuğu ve kabartma sırtlarında leylak-gümüş yansıma.',
                 'en' => 'Pearl-white paper, a blind-embossed seashell and a lilac-silver glint on its raised ridges.',
                 'ru' => 'Жемчужно-белая бумага, блинтовое тиснение раковины и сиренево-серебристый блик на её гребнях.'],
                ['sheen' => 'pearl', 'font' => 'greatvibes', 'colors' => ['ink' => '#3e3550', 'accent' => '#8f80b0']]],
        ];
        foreach ($themes as [$slug, $sort, $name, $desc, $cfg]) {
            if (DB::val('SELECT 1 FROM themes WHERE slug = ?', [$slug])) continue;
            DB::insert('themes', ['slug' => $slug, 'name_json' => jenc($name), 'description_json' => jenc($desc), 'config_json' => jenc($cfg),
                'sound_volume' => 60, 'is_active' => 1, 'sort' => $sort, 'media_rev' => 1, 'updated_at' => $now]);
        }

        $packages = [
            ['standart', 1, ['tr' => 'Standart', 'en' => 'Standard', 'ru' => 'Стандарт'], 1, 4, 1, 0,
                ['tr' => ['Seçtiğiniz temada kişisel davetiye', 'Tek davetiye dili', 'Bir etkinlik ve konum', 'Genel katılım bildirimi (RSVP)', 'QR kod ve WhatsApp paylaşımı'],
                 'en' => ['A personal invitation in your chosen theme', 'One invitation language', 'One event and venue', 'Public RSVP', 'QR code and WhatsApp sharing'],
                 'ru' => ['Персональное приглашение в выбранной теме', 'Один язык приглашения', 'Одно событие и место', 'Общая форма ответа (RSVP)', 'QR-код и отправка в WhatsApp']]],
            ['ozel', 2, ['tr' => 'Özel', 'en' => 'Signature', 'ru' => 'Особый'], 3, 5, 3, 1,
                ['tr' => ['Standart paketin tamamı', 'Üç dile kadar davetiye içeriği', 'Birden çok etkinlik ve mekân', 'Aile/kişi bazlı özel davet bağlantıları', 'Fotoğraf galerisi'],
                 'en' => ['Everything in Standard', 'Invitation content in up to three languages', 'Multiple events and venues', 'Private links per family or guest', 'Photo gallery'],
                 'ru' => ['Всё из пакета «Стандарт»', 'Содержание приглашения на трёх языках', 'Несколько событий и мест', 'Личные ссылки для семей и гостей', 'Фотогалерея']]],
            ['atolye', 3, ['tr' => 'Atölye', 'en' => 'Atelier', 'ru' => 'Ателье'], 6, 10, 3, 1,
                ['tr' => ['Özel paketin tamamı', 'Tasarımcıyla birebir çalışma', 'Tema renk ve tipografi uyarlaması', 'Öncelikli destek'],
                 'en' => ['Everything in Signature', 'One-to-one work with a designer', 'Theme colour and typography adjustments', 'Priority support'],
                 'ru' => ['Всё из пакета «Особый»', 'Индивидуальная работа с дизайнером', 'Настройка цветов и шрифтов темы', 'Приоритетная поддержка']]],
        ];
        foreach ($packages as [$code, $sort, $name, $rev, $days, $langs, $groups, $scope]) {
            if (DB::val('SELECT 1 FROM packages WHERE code = ?', [$code])) continue;
            DB::insert('packages', ['code' => $code, 'name_json' => jenc($name), 'scope_json' => jenc($scope), 'revisions' => $rev, 'delivery_days' => $days,
                'price_minor' => null, 'currency' => 'TRY', 'max_languages' => $langs, 'guest_groups' => $groups, 'is_on_sale' => 1, 'sort' => $sort, 'updated_at' => $now]);
        }

        $addons = [
            ['extra_revision', 1, ['tr' => 'Ek revizyon hakkı', 'en' => 'Extra revision', 'ru' => 'Дополнительная правка'],
                ['tr' => 'Paketinize bir revizyon hakkı ekler.', 'en' => 'Adds one revision round to your package.', 'ru' => 'Добавляет к пакету ещё один раунд правок.'], 1],
            ['express', 2, ['tr' => 'Hızlı teslim', 'en' => 'Express delivery', 'ru' => 'Срочная подготовка'],
                ['tr' => 'İlk taslak, bilgileriniz tamamlandıktan sonra öncelikli hazırlanır.', 'en' => 'Your first draft is prioritised once your details are complete.', 'ru' => 'Первый вариант готовится в приоритетном порядке после получения ваших данных.'], 0],
        ];
        foreach ($addons as [$code, $sort, $name, $desc, $extra]) {
            if (DB::val('SELECT 1 FROM addons WHERE code = ?', [$code])) continue;
            DB::insert('addons', ['code' => $code, 'name_json' => jenc($name), 'description_json' => jenc($desc), 'price_minor' => null,
                'extra_revisions' => $extra, 'is_active' => 1, 'sort' => $sort, 'updated_at' => $now]);
        }

        $defaults = ['site.name' => 'İkimizden', 'paytr.enabled' => '0', 'paytr.mode' => 'test', 'paytr.no_installment' => '1', 'paytr.max_installment' => '0',
            'smtp.port' => '587', 'smtp.encryption' => 'tls'];
        foreach ($defaults as $k => $v) {
            if (DB::val('SELECT 1 FROM settings WHERE k = ?', [$k]) === null) Settings::set($k, $v);
        }
    }
}
