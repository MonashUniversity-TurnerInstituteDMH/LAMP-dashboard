<?php
require_once __DIR__ . '/LAMP.php';
require_once __DIR__ . '/driver/v0.1/ParticipantDriver.php';

/**
 * @OA\Schema(
 *   type="string",
 *   enum={"iOS", "Android"}
 * )
 */
abstract class DeviceType extends LAMP {
    const iOS = 'iOS';
    const Android = 'Android';
}

/**
 * @OA\Schema()
 */
class ParticipantSettings extends LAMP {

    /** 
     * @OA\Property(
     *   type="string"
     * )
     */
    public $study_code = null;

    /** 
     * @OA\Property(
     *   type="string"
     * )
     */
    public $theme = null;

    /** 
     * @OA\Property(
     *   type="string"
     * )
     */
    public $language = null;

    /** 
     * @OA\Property(
     *   ref="#/components/schemas/Timestamp"
     * )
     */
    public $last_login = null;

    /** 
     * @OA\Property(
     *   ref="#/components/schemas/DeviceType"
     * )
     */
    public $device_type = null;

    /** 
     * @OA\Property(
     *   type="string"
     * )
     */
    public $emergency_contact = null;

    /** 
     * @OA\Property(
     *   type="string"
     * )
     */
    public $helpline = null;

    /** 
     * @OA\Property(
     *   ref="#/components/schemas/Timestamp"
     * )
     */
    public $blogs_checked_date = null;

    /** 
     * @OA\Property(
     *   ref="#/components/schemas/Timestamp"
     * )
     */
    public $tips_checked_date = null;

    /** 
     * @OA\Property(
     *   ref="#/components/schemas/Timestamp"
     * )
     */
    public $date_of_birth = null;

    /** 
     * @OA\Property(
     *   type="string"
     * )
     */
    public $sex = null;

    /** 
     * @OA\Property(
     *   type="string"
     * )
     */
    public $blood_type = null;
}

/**
 * @OA\Schema()
 */
class Participant extends LAMP {
    use ParticipantDriverGET_v0_1;

    /**
     * @OA\Property(
     *   ref="#/components/schemas/Identifier",
     *   x={"type"="#/components/schemas/Participant"},
     * )
     */
    public $id = null;

    /** 
     * @OA\Property(
     *   ref="#/components/schemas/Attachments"
     * )
     */
    public $attachments = null;

    /** 
     * @OA\Property(
     *   ref="#/components/schemas/ParticipantSettings"
     * )
     */
    public $settings = null;

    /** 
     * @OA\Property(
     *   type="array",
     *   @OA\Items(
     *     ref="#/components/schemas/Identifier",
     *     x={"type"="#/components/schemas/Result"},
     *   )
     * )
     */
    public $results = null;

    /** 
     * @OA\Property(
     *   type="array",
     *   @OA\Items(
     *     ref="#/components/schemas/Identifier",
     *     x={"type"="#/components/schemas/MetadataEvent"},
     *   )
     * )
     */
    public $metadata_events = null;

    /** 
     * @OA\Property(
     *   type="array",
     *   @OA\Items(
     *     ref="#/components/schemas/Identifier",
     *     x={"type"="#/components/schemas/SensorEvent"},
     *   )
     * )
     */
    public $sensor_events = null;

    /** 
     * @OA\Property(
     *   type="array",
     *   @OA\Items(
     *     ref="#/components/schemas/Identifier",
     *     x={"type"="#/components/schemas/EnvironmentEvent"},
     *   )
     * )
     */
    public $environment_events = null;

    /** 
     * @OA\Property(
     *   type="array",
     *   @OA\Items(
     *     ref="#/components/schemas/Identifier",
     *     x={"type"="#/components/schemas/FitnessEvent"},
     *   )
     * )
     */
    public $fitness_events = null;
    
    /** 
     * @OA\Get(
     *   path="/participant/{participant_id}/export",
     *   operationId="Participant::export",
     *   tags={"Participant"},
     *   x={"owner"={
     *     "$ref"="#/components/schemas/Participant"}
     *   },
     *   summary="",
     *   description="",
     *   @OA\Parameter(
     *     name="participant_id",
     *     in="path",
     *     required=true,
     *     @OA\Schema(
     *       ref="#/components/schemas/Identifier",
     *       x={"type"={
     *         "$ref"="#/components/schemas/Participant"}
     *       },
     *     )
     *   ),
     *   @OA\Parameter(ref="#/components/parameters/Export"),
     *   @OA\Parameter(ref="#/components/parameters/XPath"),
     *   @OA\Response(response=200, ref="#/components/responses/Success"),
     *   @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *   @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *   @OA\Response(response=500, ref="#/components/responses/ServerFault"),
     *   security={{"AuthorizationLegacy": {}}},
     * )
     */
    public static function export($participant_id) {

        // Prepare for a within-subject export document.
        $a = Activity::all_by_participant($participant_id);
        $r = Result::all_by_participant($participant_id) ?: [];
        $e = EnvironmentEvent::all_by_participant($participant_id) ?: [];
        $f = FitnessEvent::all_by_participant($participant_id) ?: [];

        // We have to retrieve all referenced data and synthesize the object.
        foreach ($r as &$res) {

            // Fill in the Activity for all Results.
            if ($res->activity !== null) {
                $n = array_map(function($x) {
                    return $x->name;
                }, array_filter($a, function($x) use($res) { 
                    return $x->id == $res->activity; 
                }));
                $res->activity = array_shift($n);
            } else $res->activity = array_drop($res->static_data, 'survey_name');

            // Match the result to the correct event(s), if any.
            $this_e = array_filter($e, function($x) use($res) {
                return ($x->timestamp >= $res->start_time - 1800000) && 
                       ($x->timestamp <= $res->end_time + 300000);
            });
            $this_f = array_filter($f, function($x) use($res) {
                return ($x->timestamp >= $res->start_time - 1800000) && 
                       ($x->timestamp <= $res->end_time + 300000);
            });
            $res->environment_event = count($this_e) >= 1 ? reset($this_e) : null;
            $res->fitness_event = count($this_f) >= 1 ? reset($this_f) : null;
        }
        return $r;
    }
    
    /** 
     * @OA\Get(
     *   path="/researcher/{researcher_id}/export",
     *   operationId="Participant::export_all",
     *   tags={"Participant"},
     *   x={"owner"={
     *     "$ref"="#/components/schemas/Participant"}
     *   },
     *   summary="",
     *   description="",
     *   @OA\Parameter(
     *     name="researcher_id",
     *     in="path",
     *     required=true,
     *     @OA\Schema(
     *       ref="#/components/schemas/Identifier",
     *       x={"type"={
     *         "$ref"="#/components/schemas/Researcher"}
     *       },
     *     )
     *   ),
     *   @OA\Parameter(ref="#/components/parameters/Export"),
     *   @OA\Parameter(ref="#/components/parameters/XPath"),
     *   @OA\Response(response=200, ref="#/components/responses/Success"),
     *   @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *   @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *   @OA\Response(response=500, ref="#/components/responses/ServerFault"),
     *   security={{"AuthorizationLegacy": {}}},
     * )
     */
    public static function export_all($researcher_id) {

        // Prepare for a between-subject export document.
        $a = Activity::all_by_researcher($researcher_id);
        $all_p = self::all_by_researcher($researcher_id);
        foreach ($all_p as &$p) {

            // We have to retrieve all referenced data and synthesize the object.
            $p->results = Result::all_by_participant($p->id) ?: [];
            $p->environment_events = EnvironmentEvent::all_by_participant($p->id) ?: [];
            $p->fitness_events = FitnessEvent::all_by_participant($p->id) ?: [];

            // Fill in the Activity for all Results.
            foreach ($p->results as &$res) {
                if ($res->activity !== null) {
                    $n = array_map(function($x) {
                        return $x->name;
                    }, array_filter($a, function($x) use($res) { 
                        return $x->id == $res->activity; 
                    }));
                    $res->activity = array_shift($n);
                } else $res->activity = array_drop($res->static_data, 'survey_name');
            }
        }
        return $all_p;
    }

    /** 
     * @OA\Get(
     *   path="/participant/{participant_id}/attachment/{attachment_key}",
     *   operationId="Participant::get_attachment",
     *   tags={"Participant"},
     *   x={"owner"={
     *     "$ref"="#/components/schemas/Participant"}
     *   },
     *   summary="",
     *   description="",
     *   @OA\Parameter(
     *     name="participant_id",
     *     in="path",
     *     required=true,
     *     @OA\Schema(
     *       ref="#/components/schemas/Identifier",
     *       x={"type"={
     *         "$ref"="#/components/schemas/Participant"}
     *       },
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="attachment_key",
     *     in="path",
     *     required=true,
     *     @OA\Schema(
     *       type="string",
     *     )
     *   ),
     *   @OA\Parameter(ref="#/components/parameters/Export"),
     *   @OA\Parameter(ref="#/components/parameters/XPath"),
     *   @OA\Response(response=200, ref="#/components/responses/Success"),
     *   @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *   @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *   @OA\Response(response=500, ref="#/components/responses/ServerFault"),
     *   security={{"AuthorizationLegacy": {}}},
     * )
     */
    public static function get_attachment($participant_id, $attachment_key) {
        $_id = self::parent_of($participant_id, Participant::class, Researcher::class);
        self::authorize(function($type, $value) use($participant_id, $_id) {
            if ($type == AuthType::Researcher) {
                return $value == $_id->part(1);
            } else if ($type == AuthType::Participant) {
                return $value == $participant_id;
            } else return false;
        });
        $_id = $_id->jsonSerialize();

        // Get a matching linker entry and clean it up/bail otherwise.
        $keyset = Participant::_getX($_id, Participant::class, $attachment_key);
        if ($keyset === null || !trim($keyset["ScriptContents"])) return null;
        if ($keyset["ReqPackages"] !== null)
            $keyset["ReqPackages"] = json_decode($keyset["ReqPackages"]);
        else $keyset["ReqPackages"] = [];

        // Collect input object for the RScript.
        $p = Participant::view($participant_id);
        $a = Activity::all_by_participant($participant_id);
        $r = Result::all_by_participant($participant_id) ?: [];
        $p['results'] = $r;

        // Execute the Rscript, if any.
        return RScriptRunner::execute(
            $keyset["ScriptContents"], 
            [
                "result" => [
                    "participant" => $p,
                    "activities" => $a
                ], 
                "_plot" => [
                    "type" => "pdf",
                    "width" => 800,
                    "height" => 600
                ]
            ], 
            $keyset["ReqPackages"]
        );
    }
    
    /** 
     * @OA\Get(
     *   path="/participant/{participant_id}",
     *   operationId="Participant::view",
     *   tags={"Participant"},
     *   x={"owner"={
     *     "$ref"="#/components/schemas/Participant"}
     *   },
     *   summary="",
     *   description="",
     *   @OA\Parameter(
     *     name="participant_id",
     *     in="path",
     *     required=true,
     *     @OA\Schema(
     *       ref="#/components/schemas/Identifier",
     *       x={"type"={
     *         "$ref"="#/components/schemas/Participant"}
     *       },
     *     )
     *   ),
     *   @OA\Parameter(ref="#/components/parameters/Export"),
     *   @OA\Parameter(ref="#/components/parameters/XPath"),
     *   @OA\Response(response=200, ref="#/components/responses/Success"),
     *   @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *   @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *   @OA\Response(response=500, ref="#/components/responses/ServerFault"),
     *   security={{"AuthorizationLegacy": {}}},
     * )
     */
    public static function view($participant_id) {
        if ($participant_id === 'me')
             $participant_id = self::me();
        self::authorize(function($type, $value) use($participant_id) {
            if ($type == AuthType::Researcher) {
                $_id = self::parent_of($participant_id, Participant::class, Researcher::class);
                return $value == $_id->part(1);
            } else if ($type == AuthType::Participant) {
                return $value == $participant_id;
            } else return false;
        });
        return Participant::_get($participant_id, null);
    }
    
    /** 
     * @OA\Get(
     *   path="/study/{study_id}/participant",
     *   operationId="Participant::all_by_study",
     *   tags={"Participant"},
     *   x={"owner"={
     *     "$ref"="#/components/schemas/Participant"}
     *   },
     *   summary="",
     *   description="",
     *   @OA\Parameter(
     *     name="study_id",
     *     in="path",
     *     required=true,
     *     @OA\Schema(
     *       ref="#/components/schemas/Identifier",
     *       x={"type"={
     *         "$ref"="#/components/schemas/Study"}
     *       },
     *     )
     *   ),
     *   @OA\Parameter(ref="#/components/parameters/Export"),
     *   @OA\Parameter(ref="#/components/parameters/XPath"),
     *   @OA\Response(response=200, ref="#/components/responses/Success"),
     *   @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *   @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *   @OA\Response(response=500, ref="#/components/responses/ServerFault"),
     *   security={{"AuthorizationLegacy": {}}},
     * )
     */
    public static function all_by_study($study_id) {
        return Participant::all_by_researcher($study_id);
    }
    
    /** 
     * @OA\Get(
     *   path="/researcher/{researcher_id}/participant",
     *   operationId="Participant::all_by_researcher",
     *   tags={"Participant"},
     *   x={"owner"={
     *     "$ref"="#/components/schemas/Participant"}
     *   },
     *   summary="",
     *   description="",
     *   @OA\Parameter(
     *     name="researcher_id",
     *     in="path",
     *     required=true,
     *     @OA\Schema(
     *       ref="#/components/schemas/Identifier",
     *       x={"type"={
     *         "$ref"="#/components/schemas/Researcher"}
     *       },
     *     )
     *   ),
     *   @OA\Parameter(ref="#/components/parameters/Export"),
     *   @OA\Parameter(ref="#/components/parameters/XPath"),
     *   @OA\Response(response=200, ref="#/components/responses/Success"),
     *   @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *   @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *   @OA\Response(response=500, ref="#/components/responses/ServerFault"),
     *   security={{"AuthorizationLegacy": {}}},
     * )
     */
    public static function all_by_researcher($researcher_id) {
        $_id = (new LAMPID($researcher_id))->require([Researcher::class, Study::class]);
        self::authorize(function($type, $value) use($_id) {
            return ($type == AuthType::Researcher && $value == $_id->part(1));
        });
        return Participant::_get(null, $_id->part(1));
    }

    /** 
     * @OA\Get(
     *   path="/participant",
     *   operationId="Participant::all",
     *   tags={"Participant"},
     *   x={"owner"={
     *     "$ref"="#/components/schemas/Participant"}
     *   },
     *   summary="",
     *   description="",
     *   @OA\Parameter(ref="#/components/parameters/Export"),
     *   @OA\Parameter(ref="#/components/parameters/XPath"),
     *   @OA\Response(response=200, ref="#/components/responses/Success"),
     *   @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *   @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *   @OA\Response(response=500, ref="#/components/responses/ServerFault"),
     *   security={{"AuthorizationLegacy": {}}},
     * )
     */
    public static function all() {
        self::authorize(function($type, $value) {
            return false;
        });
        return Participant::_get();
    }
}