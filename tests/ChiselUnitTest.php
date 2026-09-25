<?php

namespace Tests;

use Laravel\Chisel\Chisel;
use Laravel\Chisel\Filesystem\PendingFiles;
use Laravel\Chisel\Question;
use Laravel\Chisel\Script;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(Script::class)]
class ChiselUnitTest extends TestCase
{
    public function test_it_registers_questions_separately_from_mutations(): void
    {
        // Act

        $script = Chisel::script($this->tempDir)->questions([
            Question::multiselect(
                name: 'auth_features',
                label: 'Which authentication features would you like to enable?',
                options: [
                    'email-verification' => 'Email verification',
                    '2fa' => 'Two-factor authentication',
                    'passkeys' => 'Passkeys',
                ],
                default: ['passkeys'],
                hint: 'Use space to select, enter to confirm.',
            ),
        ]);

        // Assert

        $this->assertCount(1, $script->questions());
        $this->assertEquals('multiselect', $script->questions()[0]->type);
        $this->assertEquals('auth_features', $script->questions()[0]->name);
        $this->assertEquals(['passkeys'], $script->questions()[0]->default);
    }

    public function test_it_runs_unconditional_mutations(): void
    {
        // Arrange

        $ran = false;

        // Act

        Chisel::script($this->tempDir)
            ->apply(function () use (&$ran): void {
                $ran = true;
            })
            ->chisel([]);

        // Assert

        $this->assertTrue($ran);
    }

    public function test_it_collects_answers_with_an_ask_callback(): void
    {
        // Arrange

        $script = Chisel::script($this->tempDir)->questions([
            Question::multiselect(
                name: 'auth_features',
                label: 'Which authentication features would you like to enable?',
                options: [
                    'email-verification' => 'Email verification',
                    '2fa' => 'Two-factor authentication',
                    'passkeys' => 'Passkeys',
                ],
            ),
        ]);

        // Act

        $answers = $script
            ->collectAnswers()
            ->onQuestion(fn (Question $question): array => ['2fa']);

        // Assert

        $this->assertEquals(['auth_features' => ['2fa']], $answers->toArray());
    }

    public function test_it_keeps_provided_answers_when_collecting_answers(): void
    {
        // Arrange

        $asked = false;

        $script = Chisel::script($this->tempDir)->questions([
            Question::multiselect(
                name: 'auth_features',
                label: 'Which authentication features would you like to enable?',
                options: [
                    'email-verification' => 'Email verification',
                    '2fa' => 'Two-factor authentication',
                    'passkeys' => 'Passkeys',
                ],
            ),
        ]);

        // Act

        $answers = $script
            ->collectAnswers()
            ->onQuestion(function () use (&$asked): array {
                $asked = true;

                return ['2fa'];
            })
            ->withAnswers(['auth_features' => ['passkeys']]);

        // Assert

        $this->assertEquals(['auth_features' => ['passkeys']], $answers->toArray());
        $this->assertFalse($asked);
    }

    public function test_it_uses_defaults_automatically_when_non_interactive(): void
    {
        // Arrange

        $script = Chisel::script($this->tempDir)->questions([
            Question::multiselect(
                name: 'auth_features',
                label: 'Which authentication features would you like to enable?',
                options: [
                    'email-verification' => 'Email verification',
                    '2fa' => 'Two-factor authentication',
                    'passkeys' => 'Passkeys',
                ],
                default: ['passkeys'],
            ),
        ]);

        // Act

        $answers = $script
            ->collectAnswers()
            ->onQuestion(fn (): array => ['2fa'])
            ->interactive(false);

        // Assert

        $this->assertEquals(['auth_features' => ['passkeys']], $answers->toArray());
    }

    public function test_it_throws_when_a_required_question_has_no_answer_non_interactively(): void
    {
        // Arrange

        $script = Chisel::script($this->tempDir)->questions([
            Question::multiselect(
                name: 'auth_features',
                label: 'Which authentication features would you like to enable?',
                options: [
                    'email-verification' => 'Email verification',
                    '2fa' => 'Two-factor authentication',
                    'passkeys' => 'Passkeys',
                ],
                required: true,
            ),
        ]);

        // Anticipate

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Question [auth_features] requires an answer.');

        // Act

        $script
            ->collectAnswers()
            ->onQuestion(fn (): array => ['2fa'])
            ->interactive(false)
            ->toArray();
    }

    public function test_it_uses_an_empty_array_for_optional_unanswered_questions_non_interactively(): void
    {
        // Arrange

        $script = Chisel::script($this->tempDir)->questions([
            Question::multiselect(
                name: 'auth_features',
                label: 'Which authentication features would you like to enable?',
                options: [
                    'email-verification' => 'Email verification',
                    '2fa' => 'Two-factor authentication',
                    'passkeys' => 'Passkeys',
                ],
            ),
        ]);

        // Act

        $answers = $script
            ->collectAnswers()
            ->onQuestion(fn (): array => ['2fa'])
            ->interactive(false);

        // Assert

        $this->assertEquals(['auth_features' => []], $answers->toArray());
    }

    public function test_it_works_as_an_array_without_calling_to_array(): void
    {
        // Arrange

        $script = Chisel::script($this->tempDir)->questions([
            Question::multiselect(
                name: 'auth_features',
                label: 'Which authentication features would you like to enable?',
                options: [
                    'email-verification' => 'Email verification',
                    '2fa' => 'Two-factor authentication',
                    'passkeys' => 'Passkeys',
                ],
            ),
        ]);

        // Act

        $answers = $script
            ->collectAnswers()
            ->onQuestion(fn (Question $question): array => ['2fa']);

        // Assert

        $this->assertEquals(['2fa'], $answers['auth_features']);
        $this->assertTrue(isset($answers['auth_features']));
    }

    public function test_it_is_iterable_without_calling_to_array(): void
    {
        // Arrange

        $script = Chisel::script($this->tempDir)->questions([
            Question::multiselect(
                name: 'auth_features',
                label: 'Which authentication features would you like to enable?',
                options: [
                    'email-verification' => 'Email verification',
                    '2fa' => 'Two-factor authentication',
                    'passkeys' => 'Passkeys',
                ],
            ),
        ]);

        $answers = $script
            ->collectAnswers()
            ->onQuestion(fn (Question $question): array => ['2fa']);

        // Act

        $collected = [];
        foreach ($answers as $key => $value) {
            $collected[$key] = $value;
        }

        // Assert

        $this->assertEquals(['auth_features' => ['2fa']], $collected);
    }

    public function test_it_can_be_passed_directly_to_chisel(): void
    {
        // Arrange

        $ran = false;
        $receivedAnswers = [];

        $script = Chisel::script($this->tempDir)->questions([
            Question::multiselect(
                name: 'auth_features',
                label: 'Which authentication features would you like to enable?',
                options: [
                    'email-verification' => 'Email verification',
                    '2fa' => 'Two-factor authentication',
                    'passkeys' => 'Passkeys',
                ],
            ),
        ])->apply(function ($chisel, $answers) use (&$ran, &$receivedAnswers): void {
            $ran = true;
            $receivedAnswers = $answers;
        });

        // Act

        $script->chisel(
            $script->collectAnswers()->onQuestion(fn (): array => ['2fa']),
        );

        // Assert

        $this->assertTrue($ran);
        $this->assertEquals(['auth_features' => ['2fa']], $receivedAnswers);
    }

    public function test_it_branches_on_selected_multiselect_answers(): void
    {
        // Arrange

        $branches = [];

        // Act

        Chisel::script($this->tempDir)
            ->questions([
                Question::multiselect(
                    name: 'auth_features',
                    label: 'Which authentication features would you like to enable?',
                    options: [
                        'email-verification' => 'Email verification',
                        '2fa' => 'Two-factor authentication',
                        'passkeys' => 'Passkeys',
                    ],
                    hint: 'Use space to select, enter to confirm.',
                ),
            ])
            ->selected('auth_features', 'email-verification', then: function (Chisel $chisel) use (&$branches): void {
                $branches[] = $chisel::class;
            })
            ->selected('auth_features', 'passkeys', else: function (Chisel $chisel) use (&$branches): void {
                $branches[] = $chisel::class;
            })
            ->chisel(['auth_features' => ['email-verification']]);

        // Assert

        $this->assertEquals([Chisel::class, Chisel::class], $branches);
    }

    public function test_it_branches_when_any_multiselect_answer_is_selected(): void
    {
        // Arrange

        $branches = [];

        // Act

        Chisel::script($this->tempDir)
            ->questions([
                Question::multiselect(
                    name: 'auth_features',
                    label: 'Which authentication features would you like to enable?',
                    options: [
                        'email-verification' => 'Email verification',
                        '2fa' => 'Two-factor authentication',
                        'passkeys' => 'Passkeys',
                    ],
                    hint: 'Use space to select, enter to confirm.',
                ),
            ])
            ->selectedAny('auth_features', ['2fa', 'passkeys'], then: function (Chisel $chisel) use (&$branches): void {
                $branches[] = $chisel::class;
            })
            ->selectedAny('auth_features', ['email-verification', '2fa'], else: function (Chisel $chisel) use (&$branches): void {
                $branches[] = $chisel::class;
            })
            ->chisel(['auth_features' => ['passkeys']]);

        // Assert

        $this->assertEquals([Chisel::class, Chisel::class], $branches);
    }

    public function test_it_branches_when_all_multiselect_answers_are_selected(): void
    {
        // Arrange

        $branches = [];

        // Act

        Chisel::script($this->tempDir)
            ->questions([
                Question::multiselect(
                    name: 'auth_features',
                    label: 'Which authentication features would you like to enable?',
                    options: [
                        'email-verification' => 'Email verification',
                        '2fa' => 'Two-factor authentication',
                        'passkeys' => 'Passkeys',
                    ],
                    hint: 'Use space to select, enter to confirm.',
                ),
            ])
            ->selectedAll('auth_features', ['2fa', 'passkeys'], then: function (Chisel $chisel) use (&$branches): void {
                $branches[] = $chisel::class;
            })
            ->selectedAll('auth_features', ['email-verification', '2fa'], else: function (Chisel $chisel) use (&$branches): void {
                $branches[] = $chisel::class;
            })
            ->chisel(['auth_features' => ['2fa', 'passkeys']]);

        // Assert

        $this->assertEquals([Chisel::class, Chisel::class], $branches);
    }

    public function test_it_passes_safe_mode_from_a_script_to_its_mutations(): void
    {
        // Arrange

        file_put_contents($this->tempDir.'/a.txt', 'hello');

        $script = Chisel::script($this->tempDir)
            ->safe()
            ->apply(fn (Chisel $chisel): PendingFiles => $chisel->file('a.txt')->replace('missing', 'x'));

        // Anticipate

        $this->expectException(RuntimeException::class);

        // Act

        $script->chisel([]);
    }
}
