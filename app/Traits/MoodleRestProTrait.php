<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

trait MoodleRestProTrait
{
    /**
     * CORREGIDO: Ahora usa correctamente todos los parámetros enviados
     */
    private function make_test_user($arrPost)
    {
        $user = new \stdClass();
        $user->username = strtolower($arrPost['username']);
        $user->password = $arrPost['password'];
        $user->firstname = $arrPost['firstname'];
        $user->lastname = $arrPost['lastname']; 
        $user->email = strtolower($arrPost['email']);
        $user->auth = isset($arrPost['auth']) ? $arrPost['auth'] : 'manual';
        $user->lang = isset($arrPost['lang']) ? $arrPost['lang'] : 'es';
        $user->calendartype = isset($arrPost['calendartype']) ? $arrPost['calendartype'] : 'gregorian';
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
    }

    private function get_user($user_id, $token)
    {
        $userids = array($user_id);
        $params = array('userids' => $userids);

        $response = $this->call_moodle('core_user_get_users_by_id', $params, $token);

        $user = $this->xmlresponse_to_user($response);

        if (array_key_exists('id', $user))
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

        if (array_key_exists('id', $course))
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
        
        if (array_key_exists('id', $user)) {
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
        $domain = 'https://aulavirtualprobusiness.com';

        $serverurl = $domain . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$function_name;

        // Usar Laravel HTTP Client en lugar de curl.php
        try {
            $response = Http::timeout(30)->post($serverurl, $params);
            return $response->body();
        } catch (\Exception $e) {
            Log::error('Error en call_moodle: ' . $e->getMessage());
            return '<EXCEPTION>' . $e->getMessage() . '</EXCEPTION>';
        }
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
        
        $struct = new \StdClass();

        if(!empty($xml_tree->SINGLE->KEY->MULTIPLE->SINGLE->KEY)) {
            foreach ($xml_tree->SINGLE->KEY->MULTIPLE->SINGLE->KEY as $key) {
                $name = $key['name'];
                $value = (string)$key->VALUE;
                $struct->$name = $value;
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
     * ✅ CORREGIDO: Removido echo y mejorado manejo de errores
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
            
            $user_data_1 = $this->make_test_user($arrPost);
            
            // Log para debug
            Log::info('Creando usuario Moodle: ' . json_encode($user_data_1));
            
            $user_id_1 = $this->create_user($user_data_1, $token);
            return $user_id_1;
        } 
        catch (\Exception $e) {
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
