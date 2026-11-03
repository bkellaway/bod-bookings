<?php
declare(strict_types=1);

final class View
{
    public static function render(string $name, array $vars = [], string $layout = 'layout'): void
    {
        $content = self::partial($name, $vars);
        extract($vars, EXTR_SKIP);
        require BASE_PATH . "/views/$layout.php";
    }

    public static function partial(string $name, array $vars = []): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        require BASE_PATH . "/views/$name.php";
        return (string)ob_get_clean();
    }
}
