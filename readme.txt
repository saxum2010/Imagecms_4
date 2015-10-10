v.1.1

Для установки модуля оплаты необходимо:


1. Скопировать файл Treasurer.tpl в папку /templates/Название магазина/PaymentSystem/
2. Скопировать папку Treasurer в /application/modules/shop/classes/PaymentSystems/
3. Зайти по FTP в папку /application/modules/shop/classes
   Найти файл SPaymentSystems.php
   Отредактировать его

Найти строки

'YandexMoneySystem'=>array(
    'filePath' =>'YandexMoney/YandexMoneySystem.php',
    'listName' =>'YandexMoney',
    'class'    => null
),
'QiWiSystem'=>array(
    'filePath' =>'QiWi/QiWiSystem.php',
    'listName' =>'QiWi',
    'class'    => null
),
'PayPalSystem'=>array(
    'filePath' =>'PayPal/PayPalSystem.php',
    'listName' =>'PayPal',
    'class'    => null
),

Вставить после них

'TreasurerSystem'=>array(
    'filePath' =>'Treasurer/TreasurerSystem.php',
    'listName' =>'Казначей',
    'class'    => null
),


4. Зайти в админ панель магазина
5. Зайти в способы оплаты http://SITE.RU/admin/components/run/shop/paymentmethods/index
6. Добавить новый способ оплаты или изменить любой существующий

Название - любое (будут видеть пользователи сайта)
Валюта - гривна (предварительно создать, если нет, в валютах сайта - http://SITE.RU/admin/components/run/shop/currencies )
Галочку - активен
Заполнить поля MerchantGuid и MerchantSecretKey

Сохраните изменения

По умолчанию, после регистрации включен Тестовый режим работы
В этом режиме Вам доступна Тестовая система оплаты, с помощью которой можно проверить корректность работы модуля;
После проверки не забудьте переключиться в Рабочий режим (в Личном кабинете на закладке "Профиль").
