<?php

namespace App\Services;

class MedidasLoteService
{
    public function calcular(array $puntos): array
    {
        // El CAD puede dividir un lado recto en varios segmentos o repetir vértices.
        $vertices = [];
        foreach ($puntos as $punto) {
            if (! $vertices || $this->distancia(end($vertices), $punto) > 0.00001) {
                $vertices[] = $punto;
            }
        }
        if (count($vertices) > 1 && $this->distancia($vertices[0], end($vertices)) <= 0.00001) {
            array_pop($vertices);
        }
        do {
            $cambio = false;
            $n = count($vertices);
            for ($i = 0; $i < $n && $n > 3; $i++) {
                $a = $vertices[($i + $n - 1) % $n];
                $b = $vertices[$i];
                $c = $vertices[($i + 1) % $n];
                $base = $this->distancia($a, $c);
                $cruz = abs(($b[0] - $a[0]) * ($c[1] - $a[1]) - ($b[1] - $a[1]) * ($c[0] - $a[0]));
                $avance = ($b[0] - $a[0]) * ($c[0] - $b[0]) + ($b[1] - $a[1]) * ($c[1] - $b[1]);
                if ($base > 0 && $cruz / $base <= 0.0001 && $avance >= 0) {
                    array_splice($vertices, $i, 1);
                    $cambio = true;
                    break;
                }
            }
        } while ($cambio);
        $lados = [];
        if (count($vertices) < 3) {
            return $lados;
        }
        foreach ($vertices as $i => $inicio) {
            $fin = $vertices[($i + 1) % count($vertices)];
            $lados[] = ['numero' => $i + 1, 'metros' => round($this->distancia($inicio, $fin), 2), 'inicio' => $inicio, 'fin' => $fin];
        }

        return $lados;
    }

    private function distancia(array $a, array $b): float
    {
        return hypot($b[0] - $a[0], $b[1] - $a[1]);
    }
}
