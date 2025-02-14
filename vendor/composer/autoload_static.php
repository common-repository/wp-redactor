<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit97cfea864b8a5e134fcb6c2e94247835
{
    public static $files = array (
        '0075b5346ad7ebbc8049a212f701a189' => __DIR__ . '/..' . '/thomascgray/NooNooFluentRegex/Regex.php',
        '407d03cf391580833ad11e9b3be08ee2' => __DIR__ . '/../..' . '/includes/utils/constants.php',
        '099e865b97cfa15bb8ce3c56d0eba98c' => __DIR__ . '/../..' . '/includes/utils/environment.php',
        '7dec84e799c2179c9b8f6b66e75e1cec' => __DIR__ . '/../..' . '/includes/utils/functions.php',
        '633de30cb2ef7cd766f2a6a867478ec8' => __DIR__ . '/../..' . '/includes/utils/regex.php',
    );

    public static $prefixesPsr0 = array (
        'D' => 
        array (
            'Datasync\\' => 
            array (
                0 => __DIR__ . '/../..' . '/includes',
            ),
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixesPsr0 = ComposerStaticInit97cfea864b8a5e134fcb6c2e94247835::$prefixesPsr0;

        }, null, ClassLoader::class);
    }
}
