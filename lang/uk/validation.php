<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'Ви повинні прийняти :attribute.',
    'accepted_if' => 'Ви повинні прийняти :attribute, коли :other дорівнює :value.',
    'active_url' => 'Поле :attribute має містити дійсну URL-адресу.',
    'after' => 'Поле :attribute має бути датою після :date.',
    'after_or_equal' => 'Поле :attribute має бути датою після або рівною :date.',
    'alpha' => 'Поле :attribute має містити лише літери.',
    'alpha_dash' => 'Поле :attribute має містити лише літери, цифри, дефіси та підкреслення.',
    'alpha_num' => 'Поле :attribute має містити лише літери та цифри.',
    'any_of' => 'Поле :attribute має неприпустиме значення.',
    'array' => 'Поле :attribute має бути масивом.',
    'array_keys' => 'Поле :attribute має містити лише такі ключі: :values.',
    'ascii' => 'Поле :attribute має містити лише однобайтові алфавітно-цифрові символи та знаки.',
    'base64' => 'Поле :attribute має містити дійсний рядок Base64.',
    'before' => 'Поле :attribute має бути датою до :date.',
    'before_or_equal' => 'Поле :attribute має бути датою до або рівною :date.',
    'between' => [
        'array' => 'Поле :attribute має містити від :min до :max елементів.',
        'file' => 'Поле :attribute має бути від :min до :max кілобайтів.',
        'numeric' => 'Поле :attribute має бути від :min до :max.',
        'string' => 'Поле :attribute має містити від :min до :max символів.',
    ],
    'boolean' => 'Поле :attribute має бути true або false.',
    'can' => 'Поле :attribute містить неприпустиме значення.',
    'confirmed' => 'Підтвердження поля :attribute не збігається.',
    'contains' => 'У полі :attribute відсутнє обов\'язкове значення.',
    'current_password' => 'Невірний пароль.',
    'date' => 'Поле :attribute має містити дійсну дату.',
    'date_equals' => 'Поле :attribute має бути датою, рівною :date.',
    'date_format' => 'Поле :attribute має відповідати формату :format.',
    'decimal' => 'Поле :attribute має містити :decimal знаків після коми.',
    'declined' => 'Ви повинні відхилити :attribute.',
    'declined_if' => 'Ви повинні відхилити :attribute, коли :other дорівнює :value.',
    'different' => 'Поля :attribute та :other мають відрізнятися.',
    'digits' => 'Поле :attribute має містити :digits цифр.',
    'digits_between' => 'Поле :attribute має містити від :min до :max цифр.',
    'dimensions' => 'Поле :attribute має неприпустимі розміри зображення.',
    'distinct' => 'Поле :attribute має повторюване значення.',
    'doesnt_contain' => 'Поле :attribute не повинно містити жодного з таких значень: :values.',
    'doesnt_end_with' => 'Поле :attribute не повинно закінчуватися жодним із таких значень: :values.',
    'doesnt_start_with' => 'Поле :attribute не повинно починатися з жодного з таких значень: :values.',
    'email' => 'Поле :attribute має містити дійсну адресу електронної пошти.',
    'encoding' => 'Поле :attribute має бути закодовано в :encoding.',
    'ends_with' => 'Поле :attribute має закінчуватися одним із таких значень: :values.',
    'enum' => 'Вибране значення :attribute є неприпустимим.',
    'exists' => 'Вибране значення :attribute є неприпустимим.',
    'extensions' => 'Поле :attribute має мати одне з таких розширень: :values.',
    'file' => 'Поле :attribute має бути файлом.',
    'filled' => 'Поле :attribute має містити значення.',
    'gt' => [
        'array' => 'Поле :attribute має містити більше ніж :value елементів.',
        'file' => 'Поле :attribute має бути більше ніж :value кілобайтів.',
        'numeric' => 'Поле :attribute має бути більше ніж :value.',
        'string' => 'Поле :attribute має містити більше ніж :value символів.',
    ],
    'gte' => [
        'array' => 'Поле :attribute має містити :value елементів або більше.',
        'file' => 'Поле :attribute має бути :value кілобайтів або більше.',
        'numeric' => 'Поле :attribute має бути :value або більше.',
        'string' => 'Поле :attribute має містити :value символів або більше.',
    ],
    'hex_color' => 'Поле :attribute має містити дійсний шістнадцятковий колір.',
    'image' => 'Поле :attribute має бути зображенням.',
    'in' => 'Вибране значення :attribute є неприпустимим.',
    'in_array' => 'Поле :attribute має існувати в :other.',
    'in_array_keys' => 'Поле :attribute має містити принаймні один із таких ключів: :values.',
    'integer' => 'Поле :attribute має бути цілим числом.',
    'ip' => 'Поле :attribute має містити дійсну IP-адресу.',
    'ipv4' => 'Поле :attribute має містити дійсну IPv4-адресу.',
    'ipv6' => 'Поле :attribute має містити дійсну IPv6-адресу.',
    'json' => 'Поле :attribute має містити дійсний рядок JSON.',
    'list' => 'Поле :attribute має бути списком.',
    'lowercase' => 'Поле :attribute має містити лише малі літери.',
    'lt' => [
        'array' => 'Поле :attribute має містити менше ніж :value елементів.',
        'file' => 'Поле :attribute має бути менше ніж :value кілобайтів.',
        'numeric' => 'Поле :attribute має бути менше ніж :value.',
        'string' => 'Поле :attribute має містити менше ніж :value символів.',
    ],
    'lte' => [
        'array' => 'Поле :attribute не має містити більше ніж :value елементів.',
        'file' => 'Поле :attribute має бути :value кілобайтів або менше.',
        'numeric' => 'Поле :attribute має бути :value або менше.',
        'string' => 'Поле :attribute має містити :value символів або менше.',
    ],
    'mac_address' => 'Поле :attribute має містити дійсну MAC-адресу.',
    'max' => [
        'array' => 'Поле :attribute не має містити більше ніж :max елементів.',
        'file' => 'Поле :attribute не має бути більше ніж :max кілобайтів.',
        'numeric' => 'Поле :attribute не має бути більше ніж :max.',
        'string' => 'Поле :attribute не має містити більше ніж :max символів.',
    ],
    'max_digits' => 'Поле :attribute не має містити більше ніж :max цифр.',
    'mimes' => 'Поле :attribute має бути файлом типу: :values.',
    'mimetypes' => 'Поле :attribute має бути файлом типу: :values.',
    'min' => [
        'array' => 'Поле :attribute має містити принаймні :min елементів.',
        'file' => 'Поле :attribute має бути принаймні :min кілобайтів.',
        'numeric' => 'Поле :attribute має бути принаймні :min.',
        'string' => 'Поле :attribute має містити принаймні :min символів.',
    ],
    'min_digits' => 'Поле :attribute має містити принаймні :min цифр.',
    'missing' => 'Поле :attribute має бути відсутнім.',
    'missing_if' => 'Поле :attribute має бути відсутнім, коли :other дорівнює :value.',
    'missing_unless' => 'Поле :attribute має бути відсутнім, якщо :other не дорівнює :value.',
    'missing_with' => 'Поле :attribute має бути відсутнім, коли присутнє :values.',
    'missing_with_all' => 'Поле :attribute має бути відсутнім, коли присутні :values.',
    'multiple_of' => 'Поле :attribute має бути кратним :value.',
    'not_in' => 'Вибране значення :attribute є неприпустимим.',
    'not_regex' => 'Формат поля :attribute є неприпустимим.',
    'numeric' => 'Поле :attribute має бути числом.',
    'password' => [
        'letters' => 'Поле :attribute має містити принаймні одну літеру.',
        'mixed' => 'Поле :attribute має містити принаймні одну велику та одну малу літеру.',
        'numbers' => 'Поле :attribute має містити принаймні одну цифру.',
        'symbols' => 'Поле :attribute має містити принаймні один символ.',
        'uncompromised' => 'Значення :attribute з\'являлося в утечі даних. Будь ласка, виберіть інше значення :attribute.',
    ],
    'present' => 'Поле :attribute має бути присутнім.',
    'present_if' => 'Поле :attribute має бути присутнім, коли :other дорівнює :value.',
    'present_unless' => 'Поле :attribute має бути присутнім, якщо :other не дорівнює :value.',
    'present_with' => 'Поле :attribute має бути присутнім, коли присутнє :values.',
    'present_with_all' => 'Поле :attribute має бути присутнім, коли присутні :values.',
    'prohibited' => 'Поле :attribute заборонено.',
    'prohibited_if' => 'Поле :attribute заборонено, коли :other дорівнює :value.',
    'prohibited_if_accepted' => 'Поле :attribute заборонено, коли :other прийнято.',
    'prohibited_if_declined' => 'Поле :attribute заборонено, коли :other відхилено.',
    'prohibited_unless' => 'Поле :attribute заборонено, якщо :other не входить до :values.',
    'prohibits' => 'Поле :attribute забороняє присутність :other.',
    'regex' => 'Формат поля :attribute є неприпустимим.',
    'required' => 'Поле :attribute є обов\'язковим.',
    'required_array_keys' => 'Поле :attribute має містити записи для: :values.',
    'required_if' => 'Поле :attribute є обов\'язковим, коли :other дорівнює :value.',
    'required_if_accepted' => 'Поле :attribute є обов\'язковим, коли :other прийнято.',
    'required_if_declined' => 'Поле :attribute є обов\'язковим, коли :other відхилено.',
    'required_unless' => 'Поле :attribute є обов\'язковим, якщо :other не входить до :values.',
    'required_with' => 'Поле :attribute є обов\'язковим, коли присутнє :values.',
    'required_with_all' => 'Поле :attribute є обов\'язковим, коли присутні :values.',
    'required_without' => 'Поле :attribute є обов\'язковим, коли :values відсутнє.',
    'required_without_all' => 'Поле :attribute є обов\'язковим, коли жодне з :values не присутнє.',
    'same' => 'Поле :attribute має збігатися з :other.',
    'size' => [
        'array' => 'Поле :attribute має містити :size елементів.',
        'file' => 'Поле :attribute має бути :size кілобайтів.',
        'numeric' => 'Поле :attribute має бути :size.',
        'string' => 'Поле :attribute має містити :size символів.',
    ],
    'starts_with' => 'Поле :attribute має починатися з одного з таких значень: :values.',
    'string' => 'Поле :attribute має бути рядком.',
    'timezone' => 'Поле :attribute має містити дійсний часовий пояс.',
    'unique' => 'Таке значення :attribute вже використовується.',
    'uploaded' => 'Не вдалося завантажити :attribute.',
    'uppercase' => 'Поле :attribute має містити лише великі літери.',
    'url' => 'Поле :attribute має містити дійсну URL-адресу.',
    'ulid' => 'Поле :attribute має містити дійсний ULID.',
    'uuid' => 'Поле :attribute має містити дійсний UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
