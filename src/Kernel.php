<?php

namespace App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Artprima\PrometheusMetricsBundle\ArtprimaPrometheusMetricsBundle;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;
 }
