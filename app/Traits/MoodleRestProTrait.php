<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

trait MoodleRestProTrait
{
    /**
     * CORREGIDO: Crea usuario según documentación oficial de Moodle
     * Documentación: https://docs.moodle.org/dev/Web_service_API_functions#core_user_create_users
     */
    private function make_test_user($arrPost)
    {
        // Validar que el array no esté vacío y tenga las claves necesarias
        if (!is_array($arrPost)) {
            Log::error('make_test_user: arrPost no es un array: ' . gettype($arrPost));
            throw new \Exception('Datos de usuario inválidos - no es un array');
        }

        $requiredKeys = ['username', 'password', 'firstname', 'lastname', 'email'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $arrPost)) {
                Log::error("make_test_user: Clave faltante '$key' en arrPost: " . json_encode(array_keys($arrPost)));
                throw new \Exception("Clave faltante en datos de usuario: $key");
            }
        }

        // Crear usuario según formato exacto de Moodle
        // Solo incluir campos requeridos y opcionales válidos
        
        // Normalizar y validar email más estrictamente
        $email = strtolower(trim($arrPost['email']));
        
        // Remover espacios y caracteres problemáticos, pero mantener puntos válidos
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        // Validar que el email sea válido después de la limpieza
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::error("Email inválido después de normalización: " . $arrPost['email']);
            throw new \Exception('Email inválido: ' . $arrPost['email']);
        }
        
        // Validar que el email no tenga caracteres problemáticos para Moodle
        // Moodle puede rechazar emails con ciertos caracteres especiales
        if (preg_match('/[<>"\']/', $email)) {
            Log::error("Email contiene caracteres no permitidos: " . $email);
            throw new \Exception('Email contiene caracteres no permitidos');
        }
        
        // IMPORTANTE: Asegurar que el email esté correctamente codificado
        // Algunas versiones de Moodle pueden tener problemas con emails que tienen puntos
        // especialmente si hay múltiples puntos consecutivos o al inicio/fin de la parte local
        // Normalizar: remover puntos duplicados y puntos al inicio/fin de la parte local
        $emailParts = explode('@', $email);
        if (count($emailParts) === 2) {
            $localPart = $emailParts[0];
            $domain = $emailParts[1];
            
            // Remover puntos al inicio y final de la parte local
            $localPart = trim($localPart, '.');
            
            // Remover puntos duplicados consecutivos (aunque esto es válido en emails, Moodle puede rechazarlo)
            // Pero NO lo hacemos porque es válido según RFC
            
            // Reconstruir el email
            $email = $localPart . '@' . $domain;
            
            // Validar nuevamente después de la normalización
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::error("Email inválido después de normalización de puntos: " . $email);
                // Si falla, usar el email original
                $email = strtolower(trim($arrPost['email']));
            }
        }
        
        // Limpiar y validar firstname y lastname más estrictamente
        // Moodle puede rechazar ciertos caracteres o formatos
        $firstname = trim($arrPost['firstname']);
        $lastname = trim($arrPost['lastname']);
        
        // Remover caracteres problemáticos que Moodle puede rechazar
        // Permitir solo letras, números, espacios y algunos caracteres especiales comunes
        $firstname = preg_replace('/[^\p{L}\p{N}\s\-\'\.]/u', '', $firstname);
        $lastname = preg_replace('/[^\p{L}\p{N}\s\-\'\.]/u', '', $lastname);
        
        // Normalizar espacios múltiples a uno solo
        $firstname = preg_replace('/\s+/', ' ', $firstname);
        $lastname = preg_replace('/\s+/', ' ', $lastname);
        
        // Trim nuevamente después de la limpieza
        $firstname = trim($firstname);
        $lastname = trim($lastname);
        
        // Validar que no estén vacíos después de la limpieza
        if (empty($firstname)) {
            $firstname = 'Usuario';
        }
        if (empty($lastname)) {
            $lastname = 'Usuario';
        }
        
        // Limitar longitud según documentación de Moodle (máximo 100 caracteres)
        if (strlen($firstname) > 100) {
            $firstname = substr($firstname, 0, 100);
        }
        if (strlen($lastname) > 100) {
            $lastname = substr($lastname, 0, 100);
        }
        
        // Validar username según políticas de Moodle
        // Según documentación: "Username policy is defined in Moodle security config"
        $username = strtolower(trim($arrPost['username']));
        
        // Asegurar que username solo tenga caracteres permitidos comúnmente en Moodle
        // Generalmente: letras, números, punto, guión, guión bajo
        if (preg_match('/[^a-z0-9._-]/', $username)) {
            Log::warning("Username contiene caracteres no estándar, limpiando: " . $username);
            $username = preg_replace('/[^a-z0-9._-]/', '', $username);
        }
        
        // Validar que username no esté vacío después de limpieza
        if (empty($username)) {
            throw new \Exception('Username no puede estar vacío después de la limpieza');
        }
        
        // Construir objeto usuario con SOLO campos requeridos según documentación
        // Campos requeridos: username, firstname, lastname, email
        // Campos opcionales pero que estamos enviando: password, auth
        $user = [
            'username' => $username,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email, // Email ya normalizado y validado
        ];
        
        // Agregar password solo si está presente (es opcional según doc, pero necesario para crear usuario)
        if (isset($arrPost['password']) && !empty($arrPost['password'])) {
            $user['password'] = trim($arrPost['password']);
        }
        
        // Agregar auth solo si está presente (default es "manual" según doc)
        if (isset($arrPost['auth']) && !empty($arrPost['auth'])) {
            $user['auth'] = $arrPost['auth'];
        } else {
            $user['auth'] = 'manual'; // Default según documentación
        }
        
        // Log detallado de todos los campos que se enviarán
        Log::info("=== Datos finales para Moodle ===");
        Log::info("Username: '{$username}' (longitud: " . strlen($username) . ")");
        Log::info("Firstname: '{$firstname}' (longitud: " . strlen($firstname) . ")");
        Log::info("Lastname: '{$lastname}' (longitud: " . strlen($lastname) . ")");
        Log::info("Email: '{$email}' (longitud: " . strlen($email) . ")");
        Log::info("Auth: '{$user['auth']}'");
        Log::info("Password: " . (isset($user['password']) ? "presente (longitud: " . strlen($user['password']) . ")" : "no presente"));
        Log::info("Usuario completo: " . json_encode($user, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        
        // Solo agregar lang si está especificado (puede causar error si el idioma no está instalado)
        if (isset($arrPost['lang']) && !empty($arrPost['lang'])) {
            $user['lang'] = $arrPost['lang'];
        }
        
        // Solo agregar calendartype si está explícitamente solicitado
        // (puede causar "invalid parameter" en algunas versiones de Moodle)
        if (isset($arrPost['calendartype']) && !empty($arrPost['calendartype'])) {
            $user['calendartype'] = $arrPost['calendartype'];
        }

        // Campos opcionales según documentación
        if (isset($arrPost['city'])) {
            $user['city'] = trim($arrPost['city']);
        }
        if (isset($arrPost['country'])) {
            $user['country'] = strtoupper(trim($arrPost['country']));
        }
        if (isset($arrPost['timezone'])) {
            $user['timezone'] = $arrPost['timezone'];
        }
        if (isset($arrPost['description'])) {
            $user['description'] = $arrPost['description'];
        }
        if (isset($arrPost['idnumber'])) {
            $user['idnumber'] = $arrPost['idnumber'];
        }
        if (isset($arrPost['institution'])) {
            $user['institution'] = $arrPost['institution'];
        }
        if (isset($arrPost['department'])) {
            $user['department'] = $arrPost['department'];
        }
        if (isset($arrPost['phone1'])) {
            $user['phone1'] = $arrPost['phone1'];
        }
        if (isset($arrPost['phone2'])) {
            $user['phone2'] = $arrPost['phone2'];
        }
        if (isset($arrPost['address'])) {
            $user['address'] = $arrPost['address'];
        }
        if (isset($arrPost['mailformat'])) {
            $user['mailformat'] = (int)$arrPost['mailformat'];
        }
        if (isset($arrPost['maildisplay'])) {
            $user['maildisplay'] = (int)$arrPost['maildisplay'];
        }
        
        Log::info('Usuario Moodle preparado: ' . json_encode($user));
        return $user;
    }

    private function make_test_course($n) 
    {
        $course = new \stdClass();
        $course->fullname = 'testcourse' . $n;
        $course->shortname = 'testcourse' . $n;
        $course->categoryid = 1;
        return $course;
    }
 
    private function create_user($user, $token)
    {
        try {
        $users = array($user);
        $params = array('users' => $users);
        $response = $this->call_moodle('core_user_create_users', $params, $token);

        if ($this->xmlresponse_is_exception($response)) {
            return array(
                'status' => 'error',
                'message' => "Caught exception: " . $response
            );
        } else {
            $user_id = $this->xmlresponse_to_id($response);
            return array(
                'status' => 'success',
                'message' => "Creado " . $user_id,
                'user_id' => $user_id // ✅ AGREGADO: Retornar el ID para facilitar uso
            );
        }
        } catch (\Exception $e) {
            Log::error('Error de Moodle: ' . $e->getMessage());
            Log::error('Error de Moodle: ' . $e->getTraceAsString());
            throw new \Exception('Error de Moodle: ' . $e->getMessage());
        }
    }

    private function get_user($user_id, $token)
    {
        $userids = array($user_id);
        $params = array('userids' => $userids);

        $response = $this->call_moodle('core_user_get_users_by_id', $params, $token);

        $user = $this->xmlresponse_to_user($response);

        if (is_array($user) && array_key_exists('id', $user))
            return $user;
        else
            return null;
    }

    private function assign_role($user_id, $role_id, $context_id, $token)
    {
        $assignment = array('roleid' => $role_id, 'userid' => $user_id, 'contextid' => $context_id);
        $assignments = array($assignment);
        $params = array('assignments' => $assignments);

        $response = $this->call_moodle('core_role_assign_roles', $params, $token);
        return $response;
    }

    private function create_course($course, $token) 
    {
        $courses = array($course);
        $params = array('courses' => $courses);

        $response = $this->call_moodle('core_course_create_courses', $params, $token);

        if ($this->xmlresponse_is_exception($response))
            throw new \Exception($response);
        else {
            $course_id = $this->xmlresponse_to_id($response);
            return $course_id;
        }
    }

    private function get_course($course_id, $token)
    {
        $courseids = array($course_id);
        $params = array('options' => array('ids' => $courseids));

        $response = $this->call_moodle('core_course_get_courses', $params, $token);

        $course = $this->xmlresponse_to_course($response);

        if (is_array($course) && array_key_exists('id', $course))
            return $course;
        else
            return null;
    }

    private function enrol($user_id, $course_id, $role_id, $token) 
    {
        $enrolment = array('roleid' => $role_id, 'userid' => $user_id, 'courseid' => $course_id);
        $enrolments = array($enrolment);
        $params = array('enrolments' => $enrolments);

        $response = $this->call_moodle('enrol_manual_enrol_users', $params, $token);
        return $response;
    }

    private function enrol_curso($user_id, $course_id, $role_id, $token) 
    {
        //funcion de finalizacion de fecha por año
        $arrFecha = localtime(time(), true);
        $iYear = (1900 + $arrFecha['tm_year']);
        $iMonth = (strlen(1 + $arrFecha['tm_mon']) > 1 ? (1 + $arrFecha['tm_mon']) : '0' . (1 + $arrFecha['tm_mon']));
        $iDay = (strlen($arrFecha['tm_mday']) > 1 ? $arrFecha['tm_mday'] : '0' . $arrFecha['tm_mday']);
        $fecha_actual = $iDay . '-' . $iMonth . '-' . $iYear;
        $timestamp = strtotime($fecha_actual."+ 365 days");//sumo 1 año

        $enrolment = array('roleid' => $role_id, 'userid' => $user_id, 'courseid' => $course_id, 'timeend' => $timestamp);
        $enrolments = array($enrolment);
        $params = array('enrolments' => $enrolments);

        $response = $this->call_moodle('enrol_manual_enrol_users', $params, $token);
        return $response;
    }

    private function get_enrolled_users($course_id, $token) 
    {
        $params = array('courseid' => $course_id);

        $response = $this->call_moodle('core_enrol_get_enrolled_users', $params, $token);

        $user = $this->xmlresponse_to_user($response);
        return $user;
    }

    private function get_user_field($params, $token)
    {
        $response = $this->call_moodle('core_user_get_users', $params, $token);

        $user = $this->xmlresponse_to_user_all($response);
        
        if (is_array($user) && array_key_exists('id', $user)) {
            return array(
                'status' => 'success',
                'message' => "Existe usuario",
                'response' => $user
            );
        } else {
            return array(
                'status' => 'error',
                'message' => "No existe usuario"
            );
        }
    }

    private function call_moodle($function_name, $params, $token)
    {
        $domain = 'https://aulavirtual.probusiness.pe';

        $serverurl = $domain . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$function_name;

        // Moodle REST API requiere un formato específico para arrays anidados
        // Construir manualmente el formato que Moodle espera: users[0][username]=value
        try {
            Log::info("Llamando a Moodle: {$function_name}");
            Log::info("Parámetros enviados a Moodle: " . json_encode($params, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            // Convertir arrays anidados al formato que Moodle espera según documentación REST
            // Formato: users[0][username]=string, users[0][password]=string, etc.
            $formData = $this->buildMoodleFormData($params);
            
            // Construir el body manualmente usando http_build_query para asegurar formato exacto
            // Según documentación REST de Moodle: users[0][username]=string
            $body = http_build_query($formData, '', '&', PHP_QUERY_RFC1738);
            
            // Log del formato final que se enviará
            Log::info("Form data para Moodle (body): " . $body);
            Log::info("Form data para Moodle (array): " . json_encode($formData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            // Enviar usando asForm() que debería manejar arrays anidados correctamente
            // Si esto falla, el problema puede estar en los valores de los campos
            $response = Http::timeout(30)
                ->asForm()
                ->post($serverurl, $formData);
                
            Log::info("Respuesta de Moodle: " . $response->body());
            
            // Si hay error, loggear más detalles
            if ($response->status() !== 200 || strpos($response->body(), 'EXCEPTION') !== false) {
                Log::error("Error en respuesta de Moodle - Status: " . $response->status());
                Log::error("Body completo: " . $response->body());
            }
            
            return $response->body();
        } catch (\Exception $e) {
            Log::error('Error en call_moodle: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return '<EXCEPTION>' . $e->getMessage() . '</EXCEPTION>';
        }
    }
    
    /**
     * Construye el formato de datos que Moodle REST API espera
     * Convierte arrays anidados al formato: users[0][username]=value
     */
    private function buildMoodleFormData($params)
    {
        $formData = [];
        
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                // Para arrays anidados como users[0][username]
                foreach ($value as $index => $item) {
                    if (is_array($item)) {
                        // Array anidado: users[0][username], users[0][password], etc.
                        foreach ($item as $subKey => $subValue) {
                            // Asegurar que los valores estén correctamente codificados
                            // Para emails, asegurar que se envíen sin codificación adicional
                            // Laravel HTTP Client con asForm() manejará la codificación URL automáticamente
                            $formData["{$key}[{$index}][{$subKey}]"] = $subValue;
                            
                            // Log especial para email para debug
                            if ($subKey === 'email') {
                                Log::info("Email en form data: '{$subValue}' (longitud: " . strlen($subValue) . ", bytes: " . bin2hex($subValue) . ")");
                                // Verificar si el email tiene puntos que puedan causar problemas
                                if (strpos($subValue, '.') !== false) {
                                    $dotCount = substr_count($subValue, '.');
                                    Log::warning("Email contiene {$dotCount} punto(s) - puede causar problemas en Moodle");
                                }
                            }
                        }
                    } else {
                        // Array simple: values[0], values[1], etc.
                        $formData["{$key}[{$index}]"] = $item;
                    }
                }
            } else {
                // Valor simple
                $formData[$key] = $value;
            }
        }
        
        // Log del form data completo para debug
        Log::info("Form data construido: " . json_encode($formData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        
        return $formData;
    }

    private function xmlresponse_to_id($xml_string)
    {
        $xml_tree = new \SimpleXMLElement($xml_string);          

        $value = $xml_tree->MULTIPLE->SINGLE->KEY->VALUE;
        $id = intval(sprintf("%s", $value));

        return $id;
    }  

    private function xmlresponse_to_user($xml_string)
    {
        return $this->xmlresponse_parse_names_and_values($xml_string);
    }

    private function xmlresponse_to_course($xml_string)
    {
        return $this->xmlresponse_parse_names_and_values($xml_string);
    }

    private function xmlresponse_to_user_all($xml_string)
    {
        return $this->xmlresponse_parse_names_and_values_all($xml_string);
    }

    private function xmlresponse_parse_names_and_values($xml_string)
    {
        $xml_tree = new \SimpleXMLElement($xml_string); 

        $struct = new \StdClass();

        foreach ($xml_tree->MULTIPLE->SINGLE->KEY as $key) {
            $name = $key['name'];
            $value = (string)$key->VALUE;
            $struct->$name = $value;
        }

        return $struct;
    }

    private function xmlresponse_parse_names_and_values_all($xml_string)
    {
        $xml_tree = new \SimpleXMLElement($xml_string); 
        
        $struct = [];

        if(!empty($xml_tree->SINGLE->KEY->MULTIPLE->SINGLE->KEY)) {
            foreach ($xml_tree->SINGLE->KEY->MULTIPLE->SINGLE->KEY as $key) {
                $name = (string)$key['name'];
                $value = (string)$key->VALUE;
                $struct[$name] = $value;
            }
        }

        return $struct;
    }

    private function xmlresponse_is_exception($xml_string)
    {
        $xml_tree = new \SimpleXMLElement($xml_string);          

        $is_exception = $xml_tree->getName() == 'EXCEPTION';
        return $is_exception;
    }

    /**
     * Obtener usuario por campo específico (email, username, etc)
     */
    private function get_user_by_field($field, $value, $token)
    {
        $params = [
            'field' => $field,
            'values' => [$value]
        ];

        $response = $this->call_moodle('core_user_get_users_by_field', $params, $token);

        try {
            $xml_tree = new \SimpleXMLElement($response);
            
            // Verificar si hay resultados
            if ($xml_tree->MULTIPLE && $xml_tree->MULTIPLE->SINGLE) {
                $user = [];
                foreach ($xml_tree->MULTIPLE->SINGLE->KEY as $key) {
                    $name = (string)$key['name'];
                    $value = (string)$key->VALUE;
                    $user[$name] = $value;
                }
                
                return [
                    'status' => 'success',
                    'message' => 'Usuario encontrado',
                    'response' => $user
                ];
            }
            
            return [
                'status' => 'error',
                'message' => 'Usuario no encontrado'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error al parsear respuesta: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar usuario en Moodle
     */
    private function update_user($user_data, $token)
    {
        $users = [$user_data];
        $params = ['users' => $users];
        
        $response = $this->call_moodle('core_user_update_users', $params, $token);

        if ($this->xmlresponse_is_exception($response)) {
            return [
                'status' => 'error',
                'message' => "Error al actualizar: " . $response
            ];
        }
        
        return [
            'status' => 'success',
            'message' => "Usuario actualizado exitosamente"
        ];
    }  

    /**
     * ✅ CORREGIDO: Intenta crear usuario, si existe lo actualiza con nueva contraseña
     */
    public function createUser($arrPost)
    {
        try {
            $token = '2a41772b01afcf26da875fc1ab59bf45';
            
            // Validar datos antes de crear usuario
            if (empty($arrPost['username']) || empty($arrPost['password']) || 
                empty($arrPost['firstname']) || empty($arrPost['lastname']) || 
                empty($arrPost['email'])) {
                return array(
                    'status' => 'error',
                    'message' => 'Datos incompletos para crear usuario'
                );
            }
            
            // Primero intentar encontrar el usuario por email
            $existing_user = $this->get_user_by_field('email', $arrPost['email'], $token);
            
            if ($existing_user['status'] == 'success') {
                // Usuario existe, actualizarlo con nueva contraseña
                Log::info('Usuario ya existe en Moodle, actualizando contraseña: ' . $arrPost['email']);
                
                $user_id = $existing_user['response']['id'];
                $update_data = [
                    'id' => (int)$user_id,
                    'password' => $arrPost['password']
                ];
                
                $update_result = $this->update_user($update_data, $token);
                
                if ($update_result['status'] == 'success') {
                    return [
                        'status' => 'success',
                        'message' => 'Usuario existente actualizado',
                        'user_id' => $user_id
                    ];
                }
                
                return $update_result;
            }
            
            // Usuario no existe, crearlo
            $user_data_1 = $this->make_test_user($arrPost);
            
            // Log para debug
            Log::info('Creando nuevo usuario Moodle: ' . json_encode($user_data_1));
            
            $user_id_1 = $this->create_user($user_data_1, $token);
            return $user_id_1;
        } 
        catch (\Exception $e) {
            Log::error('Error de Moodle: ' . $e->getMessage());
            Log::error('Error de Moodle: ' . $e->getTraceAsString());
            return array(
                'status' => 'error',
                'message' => "Error de Moodle: " . $e->getMessage()
            );
        }
    }

    public function getUser($arrParams)
    {
        try {
            $token = '2a41772b01afcf26da875fc1ab59bf45';
            $user_id_1 = $this->get_user_field($arrParams, $token);
            return $user_id_1;
        } 
        catch (\Exception $e) {
            return array(
                'status' => 'error',
                'message' => "Caught exception: " . $e->getMessage()
            );
        }
    }

    /**
     * ✅ MEJORADO: Mejor manejo de errores en inscripción a cursos
     */
    public function crearCursoUsuario($arrParams)
    {
        try {
            $token = '2a41772b01afcf26da875fc1ab59bf45';

            $user_id_1 = $arrParams['id_usuario'];
            $role_id = 5;//usuario invitado

            // Array de cursos para inscribir
            $courses = [
                4, // Módulo 1: Introducción a las importaciones
                5, // Módulo 2: Importación Simplificada
                6, // Módulo 3: Importación de USA
                7, // Módulo 4: Importación Definitiva
                10, // Módulo 5: Carga Consolidada
                9  // Resumen de Módulos
            ];

            $enrolled_courses = [];
            $errors = [];

            foreach ($courses as $course_id) {
                $response_xml = $this->enrol_curso($user_id_1, $course_id, $role_id, $token);
                
                try {
                    $response_obj = new \SimpleXMLElement($response_xml);
                    
                    if (isset($response_obj->MESSAGE)) {
                        $errors[] = "Curso $course_id: " . (string)$response_obj->MESSAGE;
                    } else {
                        $enrolled_courses[] = $course_id;
                    }
                } catch (\Exception $xml_error) {
                    $errors[] = "Curso $course_id: Error parsing XML - " . $xml_error->getMessage();
                }
            }

            if (count($errors) > 0) {
                return array(
                    'status' => 'error',
                    'message' => 'Errores en inscripción: ' . implode('; ', $errors),
                    'enrolled_courses' => $enrolled_courses
                );
            } else {
                return array(
                    'status' => 'success',
                    'message' => "Usuario inscrito correctamente en todos los cursos",
                    'enrolled_courses' => $enrolled_courses
                );
            }
        } 
        catch (\Exception $e) {
            return array(
                'status' => 'error',
                'message' => "Caught exception: " . $e->getMessage()
            );
        }
    }
}
