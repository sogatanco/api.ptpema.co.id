<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employe;
use App\Models\Structure;
use App\Models\Vendor\Perusahaan;
use App\Models\Notification\NotificationEntityType;
use App\Models\Notification\Notification;
use App\Models\Notif\Notifikasi as NotifikasiBaru;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{

    public static function new($type, $recipients, $entityId)
    {
        // choose entity
        $entityType = NotificationEntityType::select('notification_entity_type.id', 'notification_entity.entity')
                    ->join('notification_entity', 'notification_entity.id', '=', 'notification_entity_type.entity_id')
                    ->where('type', $type)->first();

        if($entityType->entity === 'VENDOR'){
           $actor = $entityId;
        }else{
            $userId = Auth::user()->id;
            $actor = Employe::where('user_id', $userId)->first()->employe_id;
        }

        if($entityType->id){
            if(!is_array($recipients)){
                if(is_string($recipients)){
                    $recipientArray = array (
                        array(
                            'employe_id' => $recipients
                        )
                    );
                }else{
                    $recipientArray = $recipients->toArray();
                }
            }else{
                $recipientArray = $recipients;
            }
    
            // list sent
            $sent = [];
    
            // save notification
            for ($r=0; $r < count($recipientArray); $r++) { 
                if($actor !== $recipientArray[$r]['employe_id']){
                    if(!in_array($recipientArray[$r]['employe_id'], $sent)){
                        $data = [
                            'actor' => $actor,
                            'recipient' => $recipientArray[$r]['employe_id'],
                            'entity_type_id' => $entityType->id,
                            'entity_id' => $entityId,
                        ];
            
                        $newNotification = new Notification($data);
                        $newNotification->save();
        
                        array_push($sent, $recipientArray[$r]['employe_id']);
                    }
                }
            }
        }
    }

    public function get(){

        $employeId = Employe::where('user_id', Auth::user()->id)->first()->employe_id;

        $employeNotification = Notification::select(
                                'notifications.id', 
                                'notifications.entity_id', 
                                'notifications.created_at', 
                                'notification_entity_type.type',
                                'notification_entity_type.message', 
                                'notification_entity_type.url',
                                'notification_entity_type.query_key',
                                'notification_entity.entity',
                                'employees_hr.first_name AS actor',
                                'positions.position_name AS position'
                            )
                            ->where(['recipient' => $employeId, 'status' => 0])
                            ->join('notification_entity_type', 'notification_entity_type.id', '=', 'notifications.entity_type_id')
                            ->join('notification_entity', 'notification_entity.id', '=', 'notification_entity_type.entity_id')
                            ->join('employees_hr', 'employees_hr.employe_id', '=', 'notifications.actor')
                            ->join(config('database.connections.hr.database').'.positions as positions', 'positions.id', '=', 'employees_hr.position_id')
                            ->orderBy('notifications.id', 'DESC')
                            ->get();

            $userRoles = Auth::user()->roles;

            if(in_array('AdminVendorScm', $userRoles) || in_array('AdminVendorUmum', $userRoles)){
                $adminNotification = Notification::select(
                                    'notifications.id', 
                                    'notifications.entity_id', 
                                    'notifications.created_at', 
                                    'notification_entity_type.type',
                                    'notification_entity_type.message', 
                                    'notification_entity_type.url',
                                    'notification_entity_type.query_key',
                                    'notification_entity.entity',
                                )
                                ->where(['notifications.recipient' => $employeId, 'notifications.status' => 0])
                                ->where(function($q) { 
                                    $q->where('notification_entity.entity','LIKE','%VENDOR%');
                                    $q->orWhere('notification_entity.entity','LIKE','%TENDER%');
                                })
                                ->join('notification_entity_type', 'notification_entity_type.id', '=', 'notifications.entity_type_id')
                                ->join('notification_entity', 'notification_entity.id', '=', 'notification_entity_type.entity_id')
                                ->orderBy('notifications.id', 'DESC')
                                ->get();

                if(count($adminNotification) > 0){
                    for ($nd=0; $nd < count($adminNotification); $nd++) { 
                        $company = Perusahaan::select('bentuk_usaha', 'nama_perusahaan')
                                    ->where('id', $adminNotification[$nd]->entity_id)
                                    ->first();
                        
                        $adminNotification[$nd]->actor = $company->bentuk_usaha.' '.$company->nama_perusahaan;
                    }
    
                    $data = array_merge($employeNotification->toArray(), $adminNotification->toArray());
                }else{
                    $data = $employeNotification;
                }

            }else{
                $data = $employeNotification;
            }


        return response()->json([
            'status' => true,
            'data' => $this->gabungNotifikasiBaru($data, $employeId),
        ], 200);
    }

    /**
     * Notifikasi modul ADM, SPPD, Mobil Operasional dan Asset ada di ptpemaco_notif. Bentuknya disamakan dengan notifikasi lama
     * (id diberi awalan "n") supaya bel di frontend tidak berubah. Kegagalan database baru tidak boleh merusak bel.
     */
    private function gabungNotifikasiBaru($lama, $employeId)
    {
        if (!config('notifikasi.aktif')) {
            return $lama;
        }
        try {
            $baru = NotifikasiBaru::where('recipient', $employeId)->whereNull('read_at')->orderByDesc('id')->limit(100)->get()
                ->map(fn ($n) => [
                    'id' => 'n' . $n->id,
                    'entity_id' => null,
                    'created_at' => $n->created_at,
                    'type' => strtoupper($n->event),
                    'message' => $n->isi,
                    'url' => $n->url,
                    'query_key' => null,
                    'entity' => strtoupper($n->modul),
                    'actor' => $n->actor_name ?: '-',
                    'position' => $n->judul,
                ])->all();
        } catch (\Throwable $e) {
            report($e);

            return $lama;
        }
        $gabung = array_merge(is_array($lama) ? $lama : $lama->toArray(), $baru);
        usort($gabung, fn ($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));

        return $gabung;
    }

    public function delete($id)
    {
        if (is_string($id) && str_starts_with($id, 'n')) {
            NotifikasiBaru::where('id', (int) substr($id, 1))->where('recipient', Employe::employeId())->update(['read_at' => now()]);

            return response()->json(['status' => true, 'message' => 'Notification has been deleted'], 200);
        }
        $notification = Notification::find($id);
        $notification->status = 1;
        $notification->save();

        return response()->json([
            "status" => true,
            "message" => "Notification has been deleted"
        ], 200);
    }
}
