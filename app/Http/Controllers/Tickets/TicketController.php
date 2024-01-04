<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\Tickets\Ticket;
use Illuminate\Http\Request;
use App\Http\Requests\TicketRequest;
use App\Models\Employe;

class TicketController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TicketRequest $request)
    {
        $data = $request->validated();

        // ambil total data request
        $totalRequest = Ticket::whereYear('created_at', date("Y"))->get();

        $data['ticket_number'] = $data['subject'] === 'request' ? 'R'.date("Y").count($totalRequest)+1 : 'T'.date("Y").count($totalRequest)+1;
        $data['requester'] = Employe::employeId();

        $newRequest = new Ticket($data);
        $saved = $newRequest->save();

        if($saved){
            return response()->json([
                "status" => true,
                "message" => "New ticket has been submitted."
            ], 200);
        }else{
            throw new HttpResponseException(response([
                "message" => "Something went wrong."
            ], 500));
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Ticket $ticket)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $ticketId)
    {
        $ticket = Ticket::find($ticketId);

        if(empty($ticket)){
            throw new HttpResponseException(response([
                "message" => "Ticket not found."
            ], 404));
        }

        $updated = $ticket->update($request->all());

        if($updated){
            return response()->json([
                "status" => true,
                "message" => "Ticket has been updated.",
                "data" => $ticket
               ], 200);
        }else{
            throw new HttpResponseException(response([
                "message" => "Something went wrong."
            ], 500));
        }

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Ticket $ticket)
    {
        //
    }

    public function getTicketByEmploye()
    {
        $employe = Employe::employeId();

        $allTicket = Ticket::select('tickets.*', 'employees.first_name AS pic', 'struktur_lengkap_oke.organization_name AS pic_division')
                    ->where('requester', $employe)
                    ->join('employees', 'employees.employe_id', '=', 'tickets.pic')
                    ->join('struktur_lengkap_oke', 'struktur_lengkap_oke.employe_id', '=', 'tickets.pic')
                    ->get();

        return response()->json([
            "status" => true,
            "total" => count($allTicket),
            "data" => $allTicket
        ],200);
    }

    public function getRequestByManager(Request $request)
    {
        $query = $request->query('type');
        $manager = Employe::employeId();

        if($query === 'count'){

            $totalRequest = Ticket::where('pic', $manager)
                        ->count();

            return response()->json([
                "status" => true,
                "data" => $totalRequest
            ],200);
        }else{

            $allRequest = Ticket::select(
                            'tickets.*', 
                            'a.first_name AS requester', 
                            'b.first_name AS pic', 
                            'c.position_name AS pic_position', 
                            'd.position_name AS requester_position'
                        )
                        ->where('pic', $manager)
                        ->join('employees AS a', 'a.employe_id', '=', 'tickets.requester')
                        ->join('employees AS b', 'b.employe_id', '=', 'tickets.pic')
                        ->join('struktur_lengkap_oke AS c', 'c.employe_id', '=', 'tickets.pic')
                        ->join('struktur_lengkap_oke AS d', 'd.employe_id', '=', 'tickets.requester')
                        ->get();

            return response()->json([
                "status" => true,
                "total" => count($allRequest),
                "data" => $allRequest
            ],200);
        }
    }

    public function assignTask(Request $request)
    {
        $employe = $request->all();

        return response()->json([
            "status" => true,
            "data" => $employe
        ], 200);
    }
}
