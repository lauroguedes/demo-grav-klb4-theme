<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInite678b47c7555cb0861d015705620df54
{
    public static $prefixLengthsPsr4 = array (
        'R' => 
        array (
            'RegexBuilder\\' => 13,
        ),
        'G' => 
        array (
            'Grav\\Plugin\\ImgCaptionsPlugin\\API\\' => 34,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'RegexBuilder\\' => 
        array (
            0 => __DIR__ . '/../..' . '/classes/RegexBuilder',
        ),
        'Grav\\Plugin\\ImgCaptionsPlugin\\API\\' => 
        array (
            0 => __DIR__ . '/../..' . '/classes',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInite678b47c7555cb0861d015705620df54::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInite678b47c7555cb0861d015705620df54::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
