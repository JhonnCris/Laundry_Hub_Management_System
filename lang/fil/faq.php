<?php

return [
    'button' => 'Tulong at mga gabay',
    'title' => 'Gabay at Tulong',
    'intro' => 'Mga gabay na hakbang-hakbang, sagot sa mga karaniwang tanong, at maikling paliwanag kung paano gumagana ang sistema.',
    'tabs' => ['guide' => 'Hakbang-hakbang', 'faq' => 'Mga Tanong', 'system' => 'Gabay sa Sistema'],
    'close' => 'Isara',

    'staff_guide' => [
        ['title' => '1. Mag-clock in', 'steps' => [
            'Buksan ang Attendance Tracking. Ito ang unang makikita mo pagkatapos mag-log in.',
            'Pindutin ang Mag-clock in, at kumpirmahin.',
            'Magbubukas ang Manage Customer at Transactions. Pindutin ang "Pumunta sa Manage Customer" para magpatuloy.',
        ]],
        ['title' => '2. Maghanap o magdagdag ng customer', 'steps' => [
            'Buksan ang Manage Customer at i-type ang pangalan o numero ng telepono sa search box.',
            'Bagong customer? Pindutin ang Magdagdag ng Customer, ilagay ang first name, last name at telepono, at i-save.',
            'Pindutin ang Edit para baguhin ang telepono o email ng customer. Naka-lock ang pangalan para protektahan ang mga lumang record.',
            'Pindutin ang Maglaba para magsimula ng order para sa customer na iyon.',
        ]],
        ['title' => '3. Mag-record ng transaksyon', 'steps' => [
            'Piliin ang Drop Off (ikaw ang maglalaba) o Self Service (gagamit ng makina ang customer).',
            'Drop Off: pumili ng basket, bilangin ang mga damit, ilagay ang timbang at piliin ang uri ng serbisyo.',
            'Self Service: ilagay ang timbang, pumili ng makina, oras ng cycle at serbisyo ng makina.',
            'Magdagdag ng sabon o Downy, at meryenda o inumin kung gusto ng customer.',
            'Tingnan ang Buod sa kanan, saka pindutin ang I-save ang Transaksyon.',
        ]],
        ['title' => '4. Tumanggap ng bayad at magbigay ng resibo', 'steps' => [
            'Ilagay ang natanggap na cash. Awtomatikong lalabas ang sukli.',
            'Pindutin ang Kumpirmahin ang bayad para ma-save ang order at mabuksan ang resibo.',
            'Pindutin ang I-print ang resibo, o I-email ang resibo kung may email ang customer.',
            'Pindutin ang Bagong Transaksyon para magpatuloy sa parehong customer.',
        ]],
        ['title' => '5. Subaybayan ang labada', 'steps' => [
            'Buksan ang Active Laundry. Nakalista ang lahat ng kasalukuyang order at ang status nila.',
            'Pindutin ang berdeng button para ilipat ang order: Processing, Ready for pickup, at Claimed.',
            'Kapag naging Ready for pickup ang order, awtomatikong aabisuhan ang customer.',
            'Nagkamali ng status? Gamitin ang Undo. Hindi na maibabalik ang Claimed na order.',
            'Pindutin ang History para makita ang mga order na na-claim na.',
        ]],
        ['title' => '6. Magkansela ng order', 'steps' => [
            'Sa Active Laundry, pindutin ang pulang button na Kanselahin ang order.',
            'Mag-type ng maikling dahilan at kumpirmahin.',
            'Aalis ang order sa kabuuang benta at ibabalik ang stock nito. Staff lang ang puwedeng magkansela ng order.',
        ]],
        ['title' => '7. Tingnan ang benta at resibo', 'steps' => [
            'Buksan ang Sales Record at piliin ang Araw, Linggo o Buwan, o pindutin ang Ipakita lahat.',
            'Pindutin ang Tingnan ang resibo para buksan, i-print o i-email muli ang resibo.',
        ]],
        ['title' => '8. Stock at mga basket', 'steps' => [
            'Buksan ang Stocks, saka ang Inventory para makita ang mga stock. May pulang marka ang mga paubos na.',
            'Pindutin ang Abisuhan ang admin sa paubos na item para makita ito ng admin sa kanyang mga alerto.',
            'Pindutin ang Archive item para alisin ang expired, sira o nasirang stock.',
            'Gamitin ang dropdown sa itaas ng mga basket para ipakita lang ang Ginagamit o Available. Pindutin ang Magdagdag ng basket para magrehistro ng bago.',
        ]],
        ['title' => '9. Mga makina', 'steps' => [
            'Buksan ang Manage Machines para makita kung aling makina ang libre at gaano pa katagal ang gamit na makina.',
            'Pindutin ang Ilagay sa maintenance sa sirang makina para hindi ito mapili. Pindutin ang Ilagay na available kapag naayos na.',
        ]],
        ['title' => '10. Mag-clock out', 'steps' => [
            'Pagtapos ng shift, buksan ang Attendance Tracking at pindutin ang Mag-clock out.',
            'Magla-lock ulit ang Manage Customer at Transactions hanggang sa susunod mong shift.',
        ]],
    ],

    'staff_faq' => [
        ['q' => 'Bakit naka-lock ang Manage Customer at Transactions?', 'a' => 'Makakapagdagdag ka lang ng customer at makakapag-record ng transaksyon kapag naka-clock in ka. Buksan ang Attendance Tracking at pindutin ang Mag-clock in. Magla-lock ulit ito pagkatapos mag-clock out.'],
        ['q' => 'Nagkamali ako sa isang order. Ano ang gagawin?', 'a' => 'Kung status lang ang mali, pindutin ang Undo sa Active Laundry. Kung mali ang buong order, pindutin ang pulang Kanselahin ang order at i-record ulit.'],
        ['q' => 'Walang email ang customer kaya walang button na I-email ang resibo.', 'a' => 'Buksan ang Manage Customer, pindutin ang Edit sa customer at magdagdag ng email. Saka buksan ulit ang resibo mula sa Sales Record.'],
        ['q' => 'Sabi sa bayad, kulang ang cash sa kabuuan.', 'a' => 'Dapat pantay o mas malaki ang natanggap na cash kaysa sa babayaran. Ilagay ang buong halagang iniabot ng customer.'],
        ['q' => 'Hindi ako makapili ng basket.', 'a' => 'Ang mga available na basket lang ang nakalista. Buksan ang Inventory at tingnan ang talaan ng mga basket, o pindutin ang Magdagdag ng basket.'],
        ['q' => 'Paubos na ang isang item. Sino ang magsasabi sa admin?', 'a' => 'Pindutin ang Abisuhan ang admin sa item na iyon sa Inventory. Makikita ito ng admin bilang pulang agarang alerto at mag-re-record siya ng bagong stock.'],
        ['q' => 'Wala sa listahan ang isang makina.', 'a' => 'Ang mga available na makina lang ang mapipili. Ang makinang ginagamit o nasa maintenance ay nakatago hanggang maging libre.'],
        ['q' => 'Paano palitan ang wika, tema o laki ng teksto?', 'a' => 'Pindutin ang pangalan mo sa ibaba ng kaliwang menu, saka piliin ang Display settings o Wika.'],
        ['q' => 'Nakalimutan ko ang password ko.', 'a' => 'Mag-log out at pindutin ang "Forgot your password?" sa login page, o humingi sa admin ng bagong password sa Manage Users.'],
    ],

    'admin_guide' => [
        ['title' => '1. Basahin ang dashboard', 'steps' => [
            'Pumili ng From at To na petsa at pindutin ang Filter para palitan ang saklaw.',
            'I-click ang Active laundry, Low stock o Machines para makita ang detalye ng bawat numero.',
            'Tingnan ang Alerts: ang pula ay agarang kailangan, ang asul ay para sa impormasyon.',
        ]],
        ['title' => '2. Sales & Summary', 'steps' => [
            'Piliin ang saklaw ng petsa, saka gamitin ang Show para magpalit sa lahat ng aktibidad, kita, gastos at kinanselang order.',
            'Pindutin ang Export, piliin ang ulat at PDF o CSV, at kumpirmahin.',
            'Ang mga kinanselang order ay makikita lang dito bilang sanggunian. Hindi sila kasama sa benta.',
        ]],
        ['title' => '3. Manage Inventory', 'steps' => [
            'Ang mga paubos o ubos na item ay may pulang badge at pulang gilid.',
            'Pindutin ang Edit sa item para baguhin ang pangalan, kategorya, unit, presyo, dami o low-stock na antas.',
            'Pindutin ang Magdagdag ng item para gumawa ng bagong produkto.',
            'Para sa bagong dating na paninda, gamitin ang Stock Receiving para maitala ang invoice.',
        ]],
        ['title' => '4. Stock Receiving', 'steps' => [
            'Pindutin ang Mag-record ng resibo.',
            'Ilagay ang invoice number, petsa, supplier, ang item, ang dami sa invoice at ang dami na talagang natanggap.',
            'Ilagay ang kabuuang halaga ng invoice kung gusto mong maitala ito bilang gastos, at kumpirmahin.',
        ]],
        ['title' => '5. Manage Users', 'steps' => [
            'Ang mga bagong nag-sign up ay Pending at hindi makakapag-log in hangga\'t hindi mo inaaprubahan.',
            'Pindutin ang Aprubahan para gawin silang staff, o Edit para piliin ang Staff o Admin.',
            'Gamitin ang Magdagdag ng user para gumawa ng account, at Edit para mag-reset ng password.',
        ]],
    ],

    'admin_faq' => [
        ['q' => 'May nag-register pero hindi makapag-log in.', 'a' => 'Sinadya iyon. Buksan ang Manage Users, hanapin ang Pending na account at pindutin ang Aprubahan (o Edit at piliin ang Staff). Makakapag-log in na siya pagkatapos.'],
        ['q' => 'Bakit hindi ako makapagkansela ng order?', 'a' => 'Staff lang ang puwedeng magkansela ng order. Makikita ng admin ang mga kinanselang order sa Sales & Summary.'],
        ['q' => 'Edit o Stock Receiving: alin ang gagamitin?', 'a' => 'Gamitin ang Edit para ayusin ang pagkakamali. Gamitin ang Stock Receiving kapag may dumating na paninda para maitala ang invoice at gastos.'],
        ['q' => 'Ano ang ibig sabihin ng mga kulay ng alerto?', 'a' => 'Ang pula ay agarang kailangang aksyunan, tulad ng paubos na stock o makinang nasa maintenance. Ang asul ay para sa impormasyon lang.'],
        ['q' => 'Paano kumuha ng ulat?', 'a' => 'Pindutin ang Export sa Dashboard o sa Sales & Summary, piliin ang ulat at PDF o CSV.'],
        ['q' => 'Paano palitan ang wika?', 'a' => 'Pindutin ang pangalan mo sa ibaba ng kaliwang menu, saka Wika.'],
    ],

    'system' => [
        ['title' => 'Mga kulay at badge', 'items' => [
            'Ang pula ay agaran o error. Ang asul ay impormasyon. Ang berde ay tagumpay.',
            'Ang pulang badge na "Low stock" o "Out of stock" ay nangangahulugang kailangan nang mag-restock.',
        ]],
        ['title' => 'Mga account at tungkulin', 'items' => [
            'Ang Staff ang nagpapatakbo ng tindahan: customer, transaksyon, labada, stock at makina.',
            'Ang Admin ang may hawak ng ulat, inventory, receiving at mga user.',
            'Ang mga bagong account ay Pending hanggang aprubahan ng admin.',
        ]],
        ['title' => 'Mga setting', 'items' => [
            'Pindutin ang pangalan mo sa ibaba ng kaliwang menu para sa profile, password, display at wika.',
            'Binabago ng Display settings ang tema at laki ng teksto. Ang Wika ay nagpapalit ng teksto ng sistema, halimbawa sa Filipino.',
        ]],
        ['title' => 'Mahahabang listahan', 'items' => [
            'Sampung hilera ang ipinapakita ng bawat talaan. Gamitin ang Nakaraan at Susunod sa ilalim ng talaan para magpalit ng pahina.',
        ]],
        ['title' => 'Nahihirapan pa rin?', 'items' => [
            'Magtanong sa may-ari ng tindahan o sa admin. Kung may pulang error na mensahe, sabihin sa kanila ang eksaktong nakasulat.',
        ]],
    ],
];
