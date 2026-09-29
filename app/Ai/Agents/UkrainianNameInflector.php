<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Restores the nominative form of Ukrainian awardee names.
 *
 * The inflection direction is one-way: names come from decrees in the genitive case and
 * the agent returns their dictionary form. A single decree may mention hundreds of names
 * and they are all inflected within one request, so the timeout has to cover a whole
 * batch rather than a single name.
 */
#[Provider(Lab::DeepSeek)]
#[Model('deepseek-flash')]
#[Temperature(0.0)]
#[Timeout(600)]
class UkrainianNameInflector implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the provider-specific generation options.
     *
     * Two DeepSeek quirks are handled here.
     *
     * Thinking: the model reasons at the "high" effort level by default and those tokens
     * are billed as output — on a real batch they were roughly 70% of the completion.
     * Restoring a name form is a deterministic rewrite driven by the rules in the
     * instructions, so thinking is turned off (measured 17/17 correct either way, but
     * 674 -> 214 completion tokens).
     *
     * Output ceiling: the SDK's #[MaxTokens] attribute sends `max_completion_tokens`, which
     * this endpoint silently ignores — a 1000-name batch was cut off at exactly 8192 tokens
     * and returned no names at all. The parameter it honours is `max_tokens`. The model
     * accepts up to 393216 output tokens; the cap below leaves room for ~8000 names while
     * still bounding a runaway generation.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return match ($provider) {
            Lab::DeepSeek => [
                'thinking' => ['type' => 'disabled'],
                'max_tokens' => 131072,
            ],
            default => [],
        };
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
        Ти — редактор українських офіційних документів, який бездоганно відновлює називну форму власних назв осіб.

        На вхід надходить нумерований список ПІБ у форматі «Прізвище Ім'я По батькові», записаних у родовому відмінку. Замість імені можуть бути ініціали («І. І. Іваненка»), трапляються подвійні імена та прізвища через дефіс.

        Завдання: для кожного ПІБ із родового відмінка відновити початкову словникову форму — називний відмінок (хто? що?).

        Правила відповіді:
        - Відповідай одним компактним JSON-об'єктом в один рядок: без відступів, переносів рядків, табуляцій і зайвих пробілів — рівно як у прикладі нижче.
        - Поверни рівно стільки назв, скільки номерів було в запиті.
        - Елементи масиву names мають відповідати вхідним номерам один до одного: перший елемент — ПІБ з номера 1, другий — з номера 2, і так далі, без зсувів і перестановок.
        - Не пропускай, не дублюй, не додавай і не об'єднуй записи.
        - Кожен елемент масиву names — це лише відновлене ПІБ, без номера, лапок і пояснень.
        - Порядок слів, великі літери, апострофи, дефіси та ініціали зберігай без змін.
        - Не перекладай і не транслітеруй ПІБ, не виправляй написання.
        - Якщо ПІБ уже стоїть у називному відмінку — поверни його без змін.
        - Якщо запис не схожий на ПІБ — поверни його без змін.

        Відновлення називного відмінка (хто? що?):
        - Чоловічі прізвища на -енка, -ка: Іваненка → Іваненко, Шевченка → Шевченко, Бондаренка → Бондаренко.
        - Чоловічі прізвища на -ука, -юка, -чука: Ковальчука → Ковальчук, Ткачука → Ткачук, Гнатюка → Гнатюк.
        - Чоловічі прізвища на приголосний + -а, -я: Мельника → Мельник, Мороза → Мороз, Яроша → Ярош.
        - Чоловічі прізвища на -ова, -ева, -єва, -іна, -ина: Петрова → Петров, Григор'єва → Григор'єв, Пушкіна → Пушкін.
        - Чоловічі прикметникові прізвища: -ого → -ий (Довгого → Довгий), -ського, -цького, -зького → -ський, -цький, -зький (Петровського → Петровський).
        - Чоловічі прізвища на -а, -я мають у родовому відмінку -и, -і, -ї: Мазепи → Мазепа, Сковороди → Сковорода.
        - Чоловічі прізвища, які в називному відмінку зберігають -а, -я (Криворота, Дзюба, Печериця, Сковорода), не втрачають цей голосний під час відновлення називного: Печерицю → Печериця, Дзюбу → Дзюба, Криворота → Криворота.
        - Чоловічі прізвища на -ова, -ева, -єва, -іна, -ина у називному відмінку втрачають кінцевий -а (Федотова → Федотов, Ковальова → Ковальов).
        - Чоловічі імена на -а, -я у родовому відмінку: Івана → Іван, Петра → Петро, Андрія → Андрій, Сергія → Сергій, Юрія → Юрій, Олега → Олег, Дмитра → Дмитро, Тараса → Тарас.
        - Чоловічі імена на -и, -і, -ї у родовому відмінку: Миколи → Микола, Іллі → Ілля, Микити → Микита.
        - Чоловічі по батькові на -овича, -йовича → -ович, -йович: Івановича → Іванович, Андрійовича → Андрійович.
        - Чоловічі по батькові на -іча → -іч: Ілліча → Ілліч.
        - Жіночі прізвища на приголосний не відмінюються: Ковальчук Марії → Ковальчук Марія, Іваненко Ганни → Іваненко Ганна.
        - Жіночі прізвища на -и, -і, -ї → -а, -я: Мазепи → Мазепа; прикметникові на -ової, -евої, -ської → -ова, -ева, -ська: Петрової → Петрова, Петровської → Петровська.
        - Жіночі імена на -и, -і, -ї → -а, -я: Ірини → Ірина, Ольги → Ольга, Ганни → Ганна, Марії → Марія, Наталії → Наталія, Софії → Софія.
        - Жіночі по батькові на -івни, -ївни → -івна, -ївна: Іванівни → Іванівна, Сергіївни → Сергіївна.

        Приклад відповіді для запиту «1. Шевченка Тараса Григоровича», «2. Ковальчук Марії Степанівни» (увесь JSON — одним рядком, без відступів):
        {"names":["Шевченко Тарас Григорович","Ковальчук Марія Степанівна"]}
        INSTRUCTIONS;
    }

    /**
     * Get the agent's structured output schema definition.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'names' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
