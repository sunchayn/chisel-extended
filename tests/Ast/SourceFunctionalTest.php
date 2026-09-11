<?php

namespace Tests\Ast;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Chisel\Ast\Source;
use Laravel\Chisel\Chisel;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Source::class)]
class SourceFunctionalTest extends TestCase
{
    public function test_it_removes_imports_interfaces_and_traits_from_php_files_on_destruct(): void
    {
        // Arrange

        $path = $this->tempDir.'/User.php';

        file_put_contents($path, <<<'PHP'
            <?php

            namespace App\Models;

            use Illuminate\Database\Eloquent\Model, Illuminate\Contracts\Auth\MustVerifyEmail;
            use Laravel\Fortify\TwoFactorAuthenticatable;
            use Laravel\Sanctum\HasApiTokens;
            use Tests\Fixtures\HasFactory;

            class User extends Model implements MustVerifyEmail
            {
                use TwoFactorAuthenticatable, HasApiTokens;
                use HasFactory;

                protected $table = 'users';
            }
            PHP);

        // Act

        $file = Chisel::in($this->tempDir)
            ->php('User.php')
            ->removeImport(MustVerifyEmail::class)
            ->removeTrait('TwoFactorAuthenticatable')
            ->removeTrait('HasFactory')
            ->removeInterface('MustVerifyEmail');

        unset($file);

        // Assert

        $contents = file_get_contents($path);

        $this->assertStringContainsString('use Illuminate\Database\Eloquent\Model;', $contents);
        $this->assertStringContainsString('use HasApiTokens;', $contents);
        $this->assertStringNotContainsString('implements MustVerifyEmail', $contents);
        $this->assertStringNotContainsString('use TwoFactorAuthenticatable,', $contents);
        $this->assertStringNotContainsString('    use HasFactory;', $contents);
    }

    public function test_it_removes_imports_from_files_without_a_namespace_declaration(): void
    {
        // Arrange

        $path = $this->tempDir.'/file.php';

        file_put_contents($path, <<<'PHP'
            <?php

            use Foo\Bar;
            use Baz\Qux;

            class X {}
            PHP);

        // Act

        (new Source($path))->removeImport('Bar')->save();

        // Assert

        $contents = file_get_contents($path);

        $this->assertStringNotContainsString('use Foo\Bar;', $contents);
        $this->assertStringContainsString('use Baz\Qux;', $contents);
    }

    public function test_it_can_save_a_php_file_with_no_queued_edits(): void
    {
        // Arrange

        $path = $this->tempDir.'/SampleClass.php';

        copy(dirname(__DIR__).'/fixtures/php/SampleClass.php.stub', $path);

        $original = file_get_contents($path);

        // Act

        (new Source($path))->save();

        // Assert

        $this->assertEquals($original, file_get_contents($path));
    }
}
