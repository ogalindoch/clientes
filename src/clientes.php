<?php

namespace euroglas\clientes;

class clientes implements \euroglas\eurorest\restModuleInterface
{
    // Nombre oficial del modulo
    public function name() { return "clientes"; }

    // Descripcion del modulo
    public function description() { return "Actualiza los clientes en TheRing."; }

    // Regresa un arreglo con los permisos del modulo
    // (Si el modulo no define permisos, debe regresar un arreglo vacío)
    public function permisos()
    {
        $permisos = array();

        // $permisos['_test_'] = 'Permiso para pruebas';

        return $permisos;
    }

    // Regresa un arreglo con las rutas del modulo
    public function rutas()
    {
        $items['/clientes']['GET'] = array(
			'name' => 'Lista clientes',
			'callback' => 'listaClientes',
			'token_required' => TRUE,
		);
        $items['/clientes/[a:empresa]/']['GET'] = array(
			'name' => 'Lista clientes de empresa',
			'callback' => 'listaClientes',
			'token_required' => TRUE,
		);
		$items['/clientes/[a:empresa]/[:numCte]']['GET'] = array(
			'name' => 'Lista cliente',
			'callback' => 'listaClientes',
			'token_required' => TRUE,
		);

		$items['/contactos']['GET'] = array(
			'name' => 'Lista contactos',
			'callback' => 'listaContactos',
			'token_required' => TRUE,
		);
        $items['/contactos/[a:empresa]/']['GET'] = array(
			'name' => 'Lista contactos de empresa',
			'callback' => 'listaContactos',
			'token_required' => TRUE,
		);
		$items['/contactos/[a:empresa]/[:numCte]']['GET'] = array(
			'name' => 'Lista contactos de cliente',
			'callback' => 'listaContactos',
			'token_required' => TRUE,
		);

        // Importa los clientes
        $items['/clientes']['POST'] = array(
			'name' => 'Importa clientes',
			'callback' => 'importaClientes',
			'token_required' => TRUE,
		);

		// Importa los contactos de clientes
        $items['/contactos']['POST'] = array(
			'name' => 'Importa contactos de clientes',
			'callback' => 'importaContactos',
			'token_required' => TRUE,
		);

        // Importa los movimientos de cxc
        $items['/clientes/movimientos']['POST'] = array(
			'name' => 'Importa movimientos de clientes',
			'callback' => 'importaMovimientos',
			'token_required' => TRUE,
		);

        return $items;
    }

        /**
     * Define que secciones de configuracion requiere
     * 
     * @return array Lista de secciones requeridas
     */
    public function requiereConfig()
    {
        $secciones = array();

        $secciones[] = 'dbaccess';

        return $secciones;
    }


    /**
     * Carga UNA seccion de configuración
     * 
     * Esta función será llamada por cada seccion que indique "requiereConfig()"
     * 
     * @param string $sectionName Nombre de la sección de configuración
     * @param array $config Arreglo con la configuracion que corresponde a la seccion indicada
     * 
     */
    public function cargaConfig($sectionName, $config)
    {
        $this->config[$sectionName] = $config;
    }

	function __construct()
	{
        // NO llamamos dbInit() aquí, 
        // porque necesitamos que primero se haya cargado la configuración.
    }
    
    /**
     * Inicializa la conexión a la base de datos
     * 
     * Usa los datos del archivo de configuración para hacer la conexión al Ring
     */
    private function dbInit()
    {
        // Aún es no tenemos una conexión
        if( $this->dbRing == null )
        {
            // Tenemos el nombre del archivo de configuración de dbAccess
            // print_r($this->config);
            if( isset( $this->config['dbaccess'], $this->config['dbaccess']['config'] ) )
            {
                // Inicializa DBAccess
                //print("Cargando configuracion DB: ".$this->config['dbaccess']['config']);
                $this->dbRing = new \euroglas\dbaccess\dbaccess($this->config['dbaccess']['config']);

                if( $this->dbRing->connect('TheRing') === false )
                {
                    print($this->dbRing->getLastError());
                }
            }
        }
    }

    public function listaClientes($empresa='', $numCte='')
    {
        // Nos aseguramos de estar conectados al Ring
        $this->dbInit();

        $losClientes = $this->getClientes( urldecode($empresa), urldecode($numCte));

        //print_r($losClientes);

        die( $this->formateaRespuesta( $losClientes ) );
    }

    function listaContactos($empresa='', $numCte='')
    {
        // Nos aseguramos de estar conectados al Ring
        $this->dbInit();

        $losContactos = $this->getContactos( urldecode($empresa), urldecode($numCte));

        die( $this->formateaRespuesta( $losContactos ) );
    }

    public function getClientes( $empresa='', $numCte='', $incluyeSuspendidos=FALSE, $incluyeCxC=FALSE )
    {
        $query = '';

        $fieldList = '*';

        if( empty($empresa) || empty($numCte))
        {
            $fieldList = 'Llave, CodigoSAE, ClienteDe, Status, Nombre, Preautorizado, EsVendedor, Status, Armadora, InicialesVendedor';
        }
        $query .= "SELECT {$fieldList} FROM cliente c";

        if( !empty($empresa) && !empty($numCte))
        {
            $query .= ' JOIN clienteEnCxc cc ON (c.Llave = cc.Llave) ';
        }
        $query .= " WHERE 1=1 ";

        if( $incluyeSuspendidos )
        {
            // no limitamos el query, para incluir suspendidos
        } else {
            // Queremos solo activos
            $query .= 'AND Status = "A" ';
        }

        if( !empty($empresa) )
        {
            $query .= "AND ClienteDe = '{$empresa}' ";

            // Solo consideramos el numero de cliente,
            // SI es que tenemos empresa
            if( !empty($numCte) )
            {
                $query .= "AND CodigoSAE LIKE '%{$numCte}' ";
            }

        }

        $sth = $this->dbRing->query($query);

        //print_r($this->dbRing);

        if( $sth === false )
        {
            //print('<pre>');print_r($query);print('</pre>');
            return $this->dbRing->getLastError();
        }

        $datosDeClientes = array();
        while(	$datosDelCliente = $sth->fetch(\PDO::FETCH_ASSOC) )
        {
            $urlEmpresa = urlencode($datosDelCliente['ClienteDe']);
            $urlCodigo = urlencode($datosDelCliente['CodigoSAE']);
            //$datosDelCliente['links'] = array('self'=>"/clientes/{$urlEmpresa}/{$urlCodigo}");
            $datosDeClientes[] = $datosDelCliente;
        }

        return $datosDeClientes;
    }

    public function getContactos( $empresa='', $numCte='', $incluyeSuspendidos=FALSE )
    {
        $query = '';

        $fieldList = '*';

		/*
        if( empty($empresa) || empty($numCte))
        {
            $fieldList = 'Llave, CodigoSAE, ClienteDe, Status, Nombre, Preautorizado, EsVendedor, Status, Armadora, InicialesVendedor';
        }
		*/
        $query .= "SELECT {$fieldList} FROM clienteContacto c";

        $query .= " WHERE 1=1 ";

        if( $incluyeSuspendidos )
        {
            // no limitamos el query, para incluir suspendidos
        } else {
            // Queremos solo activos
            $query .= 'AND Status = "A" ';
        }

        if( !empty($empresa) )
        {
            $query .= "AND ClienteDe = '{$empresa}' ";

            // Solo consideramos el numero de cliente,
            // SI es que tenemos empresa
            if( !empty($numCte) )
            {
                $query .= "AND CodigoSAE LIKE '%{$numCte}' ";
            }

        }

        $sth = $this->dbRing->query($query);

        if( $sth === false )
        {
            //print('<pre>');print_r($query);print('</pre>');
            return $this->dbRing->getLastError();
        }

        $datosDeContactos = array();
        while(	$datosDelContacto = $sth->fetch(\PDO::FETCH_ASSOC) )
        {
            $urlEmpresa = urlencode($datosDelContacto['ClienteDe']);
            $urlCodigo = urlencode($datosDelContacto['CodigoSAE']);
            $datosDelContacto['links'] = array('self'=>"/contactos/{$urlEmpresa}/{$urlCodigo}");
            $datosDeContactos[] = $datosDelContacto;
        }

        return $datosDeContactos;
    }

    public function importaClientes() {
        global $DEBUG;

        // Origen
        $origen = 'SAE'; // Normalmente recibimos cosas desde SAE

        if( !empty($_POST['origen']) )
        {
            $origen = strtoupper( $_POST['origen'] );
        }

        if( !empty($_FILES['csvfile']) )
        {
            if($DEBUG) print("Importando de {$origen} desde un archivo".PHP_EOL);

            switch ($origen) {
                case 'SAE':
                    $this->importaClientesSAEFile($_FILES['csvfile']['tmp_name']);
                    print("¡Listo!");
                    break;
                default:
                    print("No se cómo importar clientes de {$origen}");
                    break;
            }

        } else {
            http_response_code(400);
            die("No se recibio el archivo");
        }
    }

    public function importaClientesSAEFile($CsvFile) {
        $dbRingW = new \euroglas\dbaccess\dbaccess($this->config['dbaccess']['config']);


        if( $dbRingW->connect('TheRingW') === false )
        {
            die(json_encode(
                $this->reportaErrorUsandoHateoas(
                    400,
                    'Error conectandose a la BD',
                    $dbRing->getLastError()
                ))
            );
        }

        $lineas = file($CsvFile);
        $separadores = array_fill(0,count($lineas),"\t");
        $csv = array_map('str_getcsv', $lineas, $separadores);

        $header = array_shift($csv);

		$header = array_map('trim',$header);

        array_walk($csv, array($this, '_combine_array'), $header);
        //print_r($csv);
        array_walk($csv, array($this, 'importaClienteSAE'), $dbRingW);


    }
    private function importaClienteSAE( $datosDelCliente, $key, $db )
    {
        if( !isset($datosDelCliente['LLAVE']))
		{
			if( isset($datosDelCliente['CodigoSAE']) && isset($datosDelCliente['ClienteDe']) )
			{
				$datosDelCliente['LLAVE'] = $datosDelCliente['ClienteDe'] . $datosDelCliente['CodigoSAE'];
			}
			else {
				return;
			}
		}

        if( ! $db->queryPrepared('mergeClienteSAE') )
        {
            $sql  = "INSERT INTO cliente ( Llave, CodigoSAE, ClienteDe, Status, Nombre, Preautorizado, EsVendedor, Clasificacion, Mercado, Automotriz, ConvAutomotriz, Carrocero, Industrial, Armadora, InicialesVendedor, CorreoVendedor, Direccion, Interior, Exterior, Colonia, Estado, Municipio, Poblacion, CodigoPostal) ";
            $sql .= "VALUES (:Llave, :CodigoSAE, :ClienteDe, :Status, :Nombre, :Preautorizado, :EsVendedor, :Clasificacion, :Mercado, :Automotriz, :ConvAutomotriz, :Carrocero, :Industrial, :Armadora, :InicialesVendedor, :CorreoVendedor, :Direccion, :Interior, :Exterior, :Colonia, :Estado, :Municipio, :Poblacion, :CodigoPostal) ";
            $sql .= "ON DUPLICATE KEY UPDATE CodigoSAE=:CodigoSAE, ClienteDe=:ClienteDe, Status=:Status, Nombre=:Nombre, Preautorizado=:Preautorizado, EsVendedor=:EsVendedor, Clasificacion=:Clasificacion, Mercado=:Mercado, Automotriz=:Automotriz, ConvAutomotriz=:ConvAutomotriz, Carrocero=:Carrocero, Industrial=:Industrial, Armadora=:Armadora, InicialesVendedor=:InicialesVendedor, CorreoVendedor=:CorreoVendedor, Direccion=:Direccion, Interior=:Interior, Exterior=:Exterior, Colonia=:Colonia, Estado=:Estado, Municipio=:Municipio, Poblacion=:Poblacion, CodigoPostal=:CodigoPostal ";

            $db->prepare($sql, 'mergeClienteSAE');
        }

        try {
            //code...
            print("{$key} Importando {$datosDelCliente['LLAVE']}...\n");
            $sth = $db->execute('mergeClienteSAE',array(
                ':Llave'=>$datosDelCliente['LLAVE'],
                ':CodigoSAE'=>$datosDelCliente['CodigoSAE'],
                ':ClienteDe'=>$datosDelCliente['ClienteDe'],
                ':Status'=>$datosDelCliente['Status'],
                ':Nombre'=>$datosDelCliente['Nombre'],
                ':Preautorizado'=>$datosDelCliente['Preautorizado'],
                ':EsVendedor'=>0,
                ':Clasificacion'=>$datosDelCliente['Clasificacion'],
                ':Mercado'=>$datosDelCliente['Mercado'],
                ':Automotriz'=>$datosDelCliente['Automotriz'],
                ':ConvAutomotriz'=>$datosDelCliente['ConvAutomotriz'],
                ':Carrocero'=>$datosDelCliente['Carrocero'],
                ':Industrial'=>$datosDelCliente['Industrial'],
                ':Armadora'=>$datosDelCliente['Armadora'],
                ':InicialesVendedor'=>$datosDelCliente['InicialesVendedor'],
                ':CorreoVendedor'=>$datosDelCliente['CorreoVendedor'],
                ':Direccion'=>$datosDelCliente['Direccion'],
                ':Interior'=>$datosDelCliente['Interior'],
                ':Exterior'=>$datosDelCliente['Exterior'],
                ':Colonia'=>$datosDelCliente['Colonia'],
                ':Estado'=>$datosDelCliente['Estado'],
                ':Municipio'=>$datosDelCliente['Municipio'],
                ':Poblacion'=>$datosDelCliente['Poblacion'],
                ':CodigoPostal'=>$datosDelCliente['CodigoPostal']
            ));
        } catch (\Throwable $th) {
            die("Error en mergeClientSAE(), linea {$key} del archivo: ". $th->getMessage());
        }

        if( isset($datosDelCliente['DiasDeCredito']) || isset($datosDelCliente['LimiteDeCredito']) )
        {
            if( ! $db->queryPrepared('mergeClienteCxc') )
            {
                if($DEBUG) print('Preparando query:mergeClienteCxcLaminados'.PHP_EOL);
                $sql  = "INSERT INTO clienteEnCxc (Llave,DiasDeCredito,LimiteDeCredito,FechaDeActualizacion) ";
                $sql .= "VALUES (:Llave, :DiasCredito, :LimiteCredito, now() ) ";
                $sql .= "ON DUPLICATE KEY UPDATE DiasDeCredito=:DiasCredito, LimiteDeCredito=:LimiteCredito, FechaDeActualizacion=now() ";

                $db->prepare($sql, 'mergeClienteCxc');
            }
        }
    }

	public function importaContactos() {
		global $DEBUG;

		// Origen
        $origen = 'SAE'; // Normalmente recibimos cosas desde SAE

        if( !empty($_POST['origen']) )
        {
            $origen = strtoupper( $_POST['origen'] );
        }


        if( !empty($_FILES['csvfile']) )
        {
            if($DEBUG) print("Importando contactos de {$origen} desde un archivo".PHP_EOL);

            switch ($origen) {
                case 'SAE':
                    $this->importaContactosSAEFile($_FILES['csvfile']['tmp_name']);
                    break;
                default:
                    print("No se cómo importar contactos de {$origen}");
                    break;
            }
        }
	}
	function importaContactosSAEFile($CsvFile) {
        $dbRingW = new \euroglas\dbaccess\dbaccess($this->config['dbaccess']['config']);

        if( $dbRingW->connect('TheRingW') === false )
        {
            die(json_encode(
                $this->reportaErrorUsandoHateoas(
                    400,
                    'Error conectandose a la BD',
                    $dbRingW->getLastError()
                ))
            );
        }

        $lineas = file($CsvFile);
        $separadores = array_fill(0,count($lineas),"\t");
        $csv = array_map('str_getcsv', $lineas, $separadores);
		$numContactos = count($csv);

        $header = array_shift($csv);

		$header = array_map('trim',$header);

		// Convierte el arreglo en un arreglo asociativo (con nombre de cada campo)
        array_walk($csv, array($this,'_combine_array'), $header);

        //print_r($csv);

		// Agrega cada contacto a la BD
        array_walk($csv, array($this, 'importaContactoSAE'), $dbRingW);
		$mensaje = "Se importaron {$numContactos} contactos de clientes";
		$this->actualizaMicroBlog($dbRingW, $mensaje);
    }
	function importaContactoSAE( $datosDelContacto, $key, $db ) {
        global $DEBUG;
        $DEBUG = TRUE;

        if( !isset($datosDelContacto['LLAVE']))
		{
			if( isset($datosDelContacto['CodigoSAE']) && isset($datosDelContacto['ClienteDe']) )
			{
				$datosDelContacto['LLAVE'] = $datosDelCliente['ClienteDe'] . $datosDelContacto['CodigoSAE'];
			}
			else {
				return;
			}
		}


        if($DEBUG) print($this->getDateTimeValue()." Procesando {$datosDelContacto['LLAVE']}".PHP_EOL);
        //if($DEBUG) print_r($datosDelCliente);
        if( ! $db->queryPrepared('mergeContactoSAE') )
        {
            if($DEBUG) print('Preparando query:mergeContactoSAE'.PHP_EOL);
            $sql  = "INSERT INTO clienteContacto ( Llave,  CodigoSAE,  ClienteDe,  Numero,  Status,  Nombre,  Tipo,  Telefono,  Email ) ";
            $sql .= "                     VALUES (:Llave, :CodigoSAE, :ClienteDe, :Numero, :Status, :Nombre, :Tipo, :Telefono, :Email ) ";
            $sql .= "ON DUPLICATE KEY UPDATE CodigoSAE=:CodigoSAE, ClienteDe=:ClienteDe, Numero=:Numero, Status=:Status, Nombre=:Nombre, Tipo=:Tipo, Telefono=:Telefono, Email=:Email ";

            $db->prepare($sql, 'mergeContactoSAE');
        }

        $sth = $db->execute('mergeContactoSAE',array(
	            ':Llave'=>$datosDelContacto['LLAVE'],
	            ':CodigoSAE'=>$datosDelContacto['CodigoSAE'],
	            ':ClienteDe'=>$datosDelContacto['ClienteDe'],
				':Numero'=>$datosDelContacto['Numero'],
	            ':Nombre'=>$datosDelContacto['Nombre'],
				':Status'=>$datosDelContacto['Status'],
				':Tipo'=>$datosDelContacto['Tipo'],
				':Telefono'=>$datosDelContacto['Telefono'],
				':Email'=>$datosDelContacto['Email'],

            ));
    }

    public function importaMovimientos()
    {
        global $DEBUG;

		//if($DEBUG) print_r($_POST);
        set_time_limit(120);

        // Origen
        $origen = 'SAE'; // Normalmente recibimos cosas desde SAE

        if( !empty($_POST['origen']) )
        {
            $origen = strtoupper( $_POST['origen'] );
        }

		if( !empty($_FILES['xmlfile']) )
        {
            if($DEBUG) print("Importando de {$origen} desde un archivo".PHP_EOL);

            switch ($origen) {
                case 'SAE':
                    // Lee y parsea el archivo XML
                    $datosXML = simplexml_load_file($_FILES['xmlfile']['tmp_name']);

                    // Importa los movimientos
                    $this->importaMovimientosSaeXml($datosXML);
                    break;

                default:
                    print("No se cómo importar clientes de {$origen} desde un archivo XML");
                    break;
            }

        }
		else {
			//if($DEBUG) print("Importando de {$origen}, pero no encontre datos que importar.".PHP_EOL);
			http_response_code(400); // 401 Unauthorized
			header('content-type: application/json');
			die(json_encode(
				$this->reportaErrorUsandoHateoas(
					400,
					'No se definieron datos a importar',
					'¿Olvido incluir el archivo XML, en el parametro xmlfile?'
				))
			);
		}
    }
    private function importaMovimientosSaeXml( $datosXML )
    {
        global $DEBUG;
        $DEBUG = TRUE;
        
        $empresa = $datosXML->attributes()->Empresa;
        $fechaDeActualizacion = $datosXML->attributes()->TimeStamp;

        if($DEBUG) print("Importando datos de {$empresa}; actualizados al {$fechaDeActualizacion}".PHP_EOL);

        $dbRingW = new \euroglas\dbaccess\dbaccess($this->config['dbaccess']['config']);
        //$dbRing = new DBAccess();

        if( $dbRingW->connect('TheRingW') === false )
        {
            die(json_encode(
                $this->reportaErrorUsandoHateoas(
                    400,
                    'Error conectandose a la BD',
                    $dbRing->getLastError()
                ))
            );
        }

		$cteCount = 0;
        foreach ($datosXML->xpath('//cliente') as $cliente)
        {

            try
    		{
				set_time_limit ( 300 ); // 30 segundos para cada cliente... debería ser más que suficiente

                $dbRingW->getCurrentConnection()->beginTransaction();

                $cteKey = $empresa . $cliente->attributes()->num ;
                print("Actualizando {$cteKey} . . . ".PHP_EOL);
				$time_start = microtime(true);

                $this->actualizaCliente( $dbRingW, $empresa, $cliente, $fechaDeActualizacion );

                $this->actualizaDocumentos( $dbRingW, $cliente, $cteKey );

                print(" {$cteKey} Listo! ".PHP_EOL);

                $dbRingW->getCurrentConnection()->commit();
				$cteCount++;
				echo "Tiempo actualizando {$cteKey} : " . (microtime(true) - $time_start);
				//ob_flush();
				//flush();
            } catch (Exception $e) {
                // An exception has been thrown
                // We must rollback the transaction
                $dbRingW->getCurrentConnection()->rollback();
                print(" {$cteKey} ERROR! ".$e->getMessage().PHP_EOL);

            }
        }
		$mensaje = "Se importaron documentos de {$cteCount} ctes de {$empresa}";
		//actualizaMicroBlog($dbRing, $empresa, $cteCount);
		$this->actualizaMicroBlog($dbRingW, $mensaje);
		print(PHP_EOL.PHP_EOL."Listo! {$cteCount} procesados".PHP_EOL);
    }

    function actualizaCliente( $db, $empresa, $cliente, $fechaDeActualizacion )
    {
        global $DEBUG;

        $cteKey = $empresa . $cliente->attributes()->num ;
        //print("Actualizando {$cteKey} . . . ".PHP_EOL);

        if( ! $db->queryPrepared('mergeClienteCxc') )
        {
            if($DEBUG) print('Preparando query:mergeClienteCxc'.PHP_EOL);
            $sql  = "INSERT INTO clienteEnCxc (Llave,DiasDeCredito,LimiteDeCredito,Saldo,FechaUltimaCompra,FechaDeActualizacion) ";
            $sql .= "VALUES ( :cteKey, :DiasCredito, :LimiteCredito, :Saldo, :Fch_ultcom, :fechaDeActualizacion) ";
            $sql .= "ON DUPLICATE KEY UPDATE DiasDeCredito=:DiasCredito, LimiteDeCredito=:LimiteCredito, Saldo=:Saldo, FechaUltimaCompra=:Fch_ultcom, FechaDeActualizacion=:fechaDeActualizacion ";

            $db->prepare($sql, 'mergeClienteCxc');
        }

        $sth = $db->execute('mergeClienteCxc',array(
                ':cteKey'=>$cteKey,
                ':DiasCredito'=>$cliente->DiasCredito,
                ':LimiteCredito'=>$cliente->LimiteCredito,
                ':Saldo'=>$cliente->Saldo,
                ':Fch_ultcom'=>$cliente->Fch_ultcom,
                ':fechaDeActualizacion'=>$fechaDeActualizacion
            ));
    }

    function actualizaDocumentos( $db, $cliente, $cteKey )
    {
        global $DEBUG;
        // Prepara el query que vamos a usar
        if( ! $db->queryPrepared('mergeDocumento') )
        {
            if($DEBUG) print('Preparando query:mergeDocumento'.PHP_EOL);
            $sql  = "INSERT INTO cxcDocumentos (Llave,Folio,Tipo,FechaDeAplicacion,FechaDeVencimiento,Cargos,Abonos,Saldo,FechaDeSaldado,Estatus,Moneda) ";
            $sql .= "VALUES (:cteKey,:folio,:Tipo,:FechaDeAplicacion,:FechaDeVencimiento,:Cargos,:Abonos,:Saldo,:FechaDeSaldado,:Estatus,:Moneda) ";
            $sql .= "ON DUPLICATE KEY UPDATE Tipo=:Tipo,FechaDeAplicacion=:FechaDeAplicacion,FechaDeVencimiento=:FechaDeVencimiento,Cargos=:Cargos,Abonos=:Abonos,Saldo=:Saldo,FechaDeSaldado=:FechaDeSaldado,Estatus=:Estatus,Moneda=:Moneda ";

            $db->prepare($sql, 'mergeDocumento');
        }

        foreach ($cliente->xpath('facturas/factura') as $factura)
        {
            // Si el cliente no tiene facturas, lo ignoramos
            if( empty($factura) )  continue;

            $folio = $factura->attributes()->num;
            print("\t{$factura->Tipo} {$folio} ".PHP_EOL);
			$time_start = microtime(true);

            $sth = $db->execute('mergeDocumento',array(
                    ':cteKey'=>$cteKey,
                    ':folio'=>$folio,
                    ':Tipo'=>$factura->Tipo,
                    ':FechaDeAplicacion'=>$factura->FechaDeAplicacion,
                    ':FechaDeVencimiento'=>$factura->FechaDeVencimiento,
                    ':Cargos'=>$factura->Cargos,
                    ':Abonos'=>$factura->Abonos,
                    ':Saldo'=>$factura->Saldo,
                    ':FechaDeSaldado'=>$factura->FechaDeSaldado,
					':Estatus'=>$factura->Estatus,
					':Moneda'=>$factura->Moneda
                ));

            $this->actualizaMovimientosDelDocumento( $db, $cteKey, $folio, $factura );
			echo "\tTiempo actualizando {$factura->Tipo} {$folio} : " . (microtime(true) - $time_start);
        }
    }

    function actualizaMovimientosDelDocumento( $db, $cteKey, $folio, $documento )
    {
        global $DEBUG;

        // Prepara el query que vamos a usar
        if( ! $db->queryPrepared('agregaMovimiento') )
        {
            if($DEBUG) print('Preparando query:mergeDocumento'.PHP_EOL);
            $sql  = "INSERT INTO cxcMovimientos (Llave,FolioDelDocumento,Tipo,FechaDeAplicacion,FechaDeVencimiento,Cargos,Abonos,Saldo,FechaDeSaldado,Moneda) ";
            $sql .= "VALUES (:cteKey,:folioDelDocto,:tipoDeMovimiento,:FechaDeAplicacion,:FechaDeVencimiento,:Cargos,:Abonos,:Saldo,:FechaDeSaldado,:Moneda) ";

            $db->prepare($sql, 'agregaMovimiento');
        }

        // Borra los movimientos de este documento, para generarlos de nuevo
        $cuenta = $db->exec( "DELETE FROM cxcMovimientos WHERE Llave='{$cteKey}' AND FolioDelDocumento='{$folio}'" );

        // Genera los movimientos
        foreach ($documento->xpath('movimientos/movimiento') as $movimiento) {

            $tipoDeMovimiento = $movimiento->attributes()->Tipo;
            print("\t\t{$tipoDeMovimiento} ".PHP_EOL);

            $sth = $db->execute('agregaMovimiento',array(
                    ':cteKey'=>$cteKey,
                    ':folioDelDocto'=>$folio,
                    ':tipoDeMovimiento'=>$tipoDeMovimiento,
                    ':FechaDeAplicacion'=>$movimiento->FechaDeAplicacion,
                    ':FechaDeVencimiento'=>$movimiento->FechaDeVencimiento,
                    ':Cargos'=>$movimiento->Cargos,
                    ':Abonos'=>$movimiento->Abonos,
                    ':Saldo'=>$movimiento->Saldo,
                    ':FechaDeSaldado'=>$movimiento->FechaDeSaldado,
					':Moneda'=>$movimiento->Moneda
                ));
        }
    }


    private function formateaRespuesta($datos)
	{
        $etag = hash("crc32b", serialize($datos) );
        // manda el eTag, para la próxima
        header("Etag: $etag"); // Needs to be sent, even for 304

        if( isset($_SERVER['HTTP_IF_NONE_MATCH']) )
        {
            if( trim($_SERVER['HTTP_IF_NONE_MATCH']) == $etag )
            {
                http_response_code(304); // 304 Not Modified
                die(); // The 304 response MUST NOT contain a message-body
            }
        } 

        
		if(
			(isset( $_SERVER['HTTP_ACCEPT'] ) && stripos($_SERVER['HTTP_ACCEPT'], 'JSON')!==false)
			||
			(isset( $_REQUEST['format'] ) && stripos($_REQUEST['format'], 'JSON')!==false)
		)
		{
            //print("Formateando JSON");
            //print_r($datos);

            header('content-type: application/json');
            $enJson = json_encode($datos, JSON_THROW_ON_ERROR); 
            
            //print($enJson);

			return( $enJson );
		}
		else if(
			(isset( $_SERVER['HTTP_ACCEPT'] ) && stripos($_SERVER['HTTP_ACCEPT'], 'CSV')!==false)
			||
			(isset( $_REQUEST['format'] ) && stripos($_REQUEST['format'], 'CSV')!==false)
		)
		{
			$output = fopen("php://output",'w') or die("Can't open php://output");
			header("Content-Type:application/csv");
			foreach($datos as $dato) {
				if(is_array($dato))
				{
    				fputcsv($output, $dato);
				} else {
					fputs($output, $dato . "\n");
				}
			}
			fclose($output) or die("Can't close php://output");
			return;
			//return( json_encode( $datos ) );
		}
		else
		{
			// Formato no definido
			header('content-type: text/plain');
			return( print_r($datos, TRUE) );
		}
    }
    private function _combine_array(&$row, $key, $header) {

        if( count($row) != count($header) )
        {
            print($this->getDateTimeValue()." ERROR: El renglon {$key} tiene ".count($row).' campos, pero esperabamos '.count($header).PHP_EOL);
            print_r($row);
            return;
        }
        $row = array_combine($header, $row);
    }
    private function getDateTimeValue( $intDate = null ) {
        $time    = microtime(true);
    	$dFormat = "H:i:s";
    	$mSecs   =  $time - floor($time);
        $mSecs   = round( $mSecs, 4 );
    	$mSecs   = substr($mSecs,2) ;

        return(sprintf('%s+%s', date($dFormat), $mSecs ) );
    }
    private function actualizaMicroBlog( $db, $mensaje ) {

		if( ! $db->queryPrepared('agregaMicroBlog') )
		{
			$sql  = "INSERT INTO microblog (modulo, mensaje, quien, permisos, url) ";
			$sql .= "VALUES (:modulo, :mensaje, :quien, :permisos, :url) ";

			$db->prepare($sql, 'agregaMicroBlog');
		}

		$sth = $db->execute('agregaMicroBlog',array(
				':modulo'=>'API clientes',
				':mensaje'=> $mensaje,
				':quien'=>'sistema',
				':permisos'=> '["administer-site-configuration"]',
				':url'=>'/cliente'
			));
	}
    /**
     * Formatea el error usando estandar de HATEOAS
     * @param integer $code Codigo HTTP o Codigo de error interno
     * @param string  $userMessage Mensaje a mostrar al usuario (normalmente, con vocabulario sencillo)
     * @param string  $internalMessage Mensaje para usuarios avanzados, posiblemente con detalles técnicos
     * @param string  $moreInfoUrl URL con una explicación del error, o documentacion relacionada
     * @return string Error en un arreglo, para ser enviado al cliente usando json_encode().
    */
    private function reportaErrorUsandoHateoas($code=400, $userMessage='', $internalMessage='',$moreInfoUrl=null)
    {
        $hateoas = array(
            'links' => array(
                'self' => $_SERVER['REQUEST_URI'],
            ),
        );
        $hateoasErrors = array();
        $hateoasErrors[] = array(
            'code' => $code,
            'userMessage' => $userMessage,
            'internalMessage' => $internalMessage,
            'more info' => $moreInfoUrl,
        );

        $hateoas['errors'] = $hateoasErrors;

        // No lo codificamos con Jason aqui, para que el cliente pueda agregar cosas mas facilmente
        return($hateoas);
    }


    private $config = array();
	private $dbRing = null;
}