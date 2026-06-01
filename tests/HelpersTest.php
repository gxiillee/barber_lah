<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../clases/helpers.php';

class HelpersTest extends TestCase
{
    // ---------------------------------------------------------------
    // h() — escapado XSS
    // ---------------------------------------------------------------

    public function testHEscapaComillasDobles(): void
    {
        $this->assertEquals('&quot;hola&quot;', h('"hola"'));
    }

    public function testHEscapaTagsHTML(): void
    {
        $this->assertEquals('&lt;script&gt;', h('<script>'));
    }

    public function testHConNumeroDevuelveString(): void
    {
        $this->assertIsString(h(42));
    }

    public function testHConStringNormalNoModifica(): void
    {
        $this->assertEquals('Hassan', h('Hassan'));
    }

    // ---------------------------------------------------------------
    // nombreMes()
    // ---------------------------------------------------------------

    public function testNombreMesEnero(): void
    {
        $this->assertEquals('enero', nombreMes(1));
    }

    public function testNombreMesJunio(): void
    {
        $this->assertEquals('junio', nombreMes(6));
    }

    public function testNombreMesDiciembre(): void
    {
        $this->assertEquals('diciembre', nombreMes(12));
    }

    public function testNombreMesFueraDeRangoDevuelveVacio(): void
    {
        $this->assertEquals('', nombreMes(13));
    }

    // ---------------------------------------------------------------
    // nombreDia() — recibe int ISO (1=lunes, 7=domingo)
    // ---------------------------------------------------------------

    public function testNombreDiaLunes(): void
    {
        $this->assertEquals('lunes', nombreDia(1));
    }

    public function testNombreDiaSabado(): void
    {
        $this->assertEquals('sabado', nombreDia(6));
    }

    public function testNombreDiaDomingo(): void
    {
        $this->assertEquals('domingo', nombreDia(7));
    }

    public function testNombreDiaFueraDeRangoDevuelveVacio(): void
    {
        $this->assertEquals('', nombreDia(8));
    }

    // ---------------------------------------------------------------
    // nombreDiaCorto()
    // ---------------------------------------------------------------

    public function testNombreDiaCortoLunes(): void
    {
        $this->assertEquals('Lun', nombreDiaCorto(1));
    }

    public function testNombreDiaCortoViernes(): void
    {
        $this->assertEquals('Vie', nombreDiaCorto(5));
    }

    public function testNombreDiaCortoDomingo(): void
    {
        $this->assertEquals('Dom', nombreDiaCorto(7));
    }

    // ---------------------------------------------------------------
    // nombreMesCorto()
    // ---------------------------------------------------------------

    public function testNombreMesCortoEnero(): void
    {
        $this->assertEquals('Ene', nombreMesCorto(1));
    }

    public function testNombreMesCortoJunio(): void
    {
        $this->assertEquals('Jun', nombreMesCorto(6));
    }

    public function testNombreMesCortoDiciembre(): void
    {
        $this->assertEquals('Dic', nombreMesCorto(12));
    }

    // ---------------------------------------------------------------
    // fechaHumana()
    // ---------------------------------------------------------------

    public function testFechaHumanaLunes(): void
    {
        // 2025-06-16 es lunes
        $this->assertEquals('lunes 16 de junio', fechaHumana('2025-06-16'));
    }

    public function testFechaHumanaSabado(): void
    {
        // 2025-06-14 es sábado
        $this->assertEquals('sabado 14 de junio', fechaHumana('2025-06-14'));
    }

    public function testFechaHumanaPrimeroDeAño(): void
    {
        // 2025-01-01 es miércoles
        $this->assertEquals('miercoles 1 de enero', fechaHumana('2025-01-01'));
    }

    // ---------------------------------------------------------------
    // esFechaValida()
    // ---------------------------------------------------------------

    public function testFechaValidaFormatoCorrecto(): void
    {
        $this->assertTrue(esFechaValida('2025-12-25'));
    }

    public function testFechaValidaFormatoIncorrecto(): void
    {
        // dd-mm-yyyy no es Y-m-d
        $this->assertFalse(esFechaValida('25-12-2025'));
    }

    public function testFechaValidaDiaImposible(): void
    {
        // febrero no tiene 31 días
        $this->assertFalse(esFechaValida('2025-02-31'));
    }

    public function testFechaValidaMesImposible(): void
    {
        $this->assertFalse(esFechaValida('2025-13-01'));
    }

    public function testFechaValidaCadenaAleatoria(): void
    {
        $this->assertFalse(esFechaValida('no-es-fecha'));
    }

    // ---------------------------------------------------------------
    // obtenerLunesDeSemanaStr()
    // ---------------------------------------------------------------

    public function testLunesDeUnLunes(): void
    {
        // Si el día ya es lunes, devuelve el mismo día
        $this->assertEquals('2025-06-16', obtenerLunesDeSemanaStr('2025-06-16'));
    }

    public function testLunesDeUnMiercoles(): void
    {
        // 2025-06-18 es miércoles → su lunes es 2025-06-16
        $this->assertEquals('2025-06-16', obtenerLunesDeSemanaStr('2025-06-18'));
    }

    public function testLunesDeUnDomingo(): void
    {
        // 2025-06-22 es domingo → su lunes es 2025-06-16
        $this->assertEquals('2025-06-16', obtenerLunesDeSemanaStr('2025-06-22'));
    }

    // ---------------------------------------------------------------
    // obtenerTituloSemana()
    // ---------------------------------------------------------------

    public function testTituloSemanaUnMesSolo(): void
    {
        // Semana del 16 al 22 de junio, toda en junio
        $lunes = new DateTimeImmutable('2025-06-16');
        $this->assertEquals('junio 2025', obtenerTituloSemana($lunes));
    }

    public function testTituloSemanaDosMeses(): void
    {
        // Semana del 30 de junio al 6 de julio, abarca dos meses
        $lunes = new DateTimeImmutable('2025-06-30');
        $this->assertEquals('junio / julio 2025', obtenerTituloSemana($lunes));
    }

    // ---------------------------------------------------------------
    // calcularBotonesNavegacion()
    // ---------------------------------------------------------------

    public function testPuedeAvanzarSiSiguienteEsMenorQueMaxima(): void
    {
        $inicio  = new DateTimeImmutable('2025-06-16');
        $actual  = new DateTimeImmutable('2025-06-16');
        $maxima  = new DateTimeImmutable('2025-07-14');

        $resultado = calcularBotonesNavegacion($inicio, $actual, $maxima);

        $this->assertTrue($resultado['puede_avanzar']);
    }

    public function testNoPuedeRetrocederSiEsLaSemanaActual(): void
    {
        $inicio  = new DateTimeImmutable('2025-06-16');
        $actual  = new DateTimeImmutable('2025-06-16');
        $maxima  = new DateTimeImmutable('2025-07-14');

        $resultado = calcularBotonesNavegacion($inicio, $actual, $maxima);

        $this->assertFalse($resultado['puede_retroceder']);
    }

    public function testFechasPrevYNextCorrectas(): void
    {
        $inicio  = new DateTimeImmutable('2025-06-16');
        $actual  = new DateTimeImmutable('2025-06-16');
        $maxima  = new DateTimeImmutable('2025-07-14');

        $resultado = calcularBotonesNavegacion($inicio, $actual, $maxima);

        $this->assertEquals('2025-06-09', $resultado['prev']);
        $this->assertEquals('2025-06-23', $resultado['next']);
    }
}