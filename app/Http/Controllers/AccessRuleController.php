<?php

namespace App\Http\Controllers;

use App\Models\Access;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccessRequest;
use App\Http\Requests\UpdateAccessRequest;
use App\Models\Member;
use App\Models\Visitor;
use Illuminate\Support\Facades\DB;

class AccessRuleController extends Controller
{
    // Método estático 'findAccessByTime' na classe 'AccessController'
    public static function findAccessByTime($time, $gate)
    {
        // Divide a string de tempo em data e hora
        $timeParts = explode(' ', $time);
        $date = $timeParts[0];
        $time = $timeParts[1];

        // Substitui '-' por ':' na hora
        $time = str_replace('-', ':', $time);
        $datetime = $date . ' ' . $time;
        $time = strtotime($datetime);

        // Define o intervalo de tempo para buscar acessos: 15 segundos antes e depois do horário fornecido
        $startTime = date('Y-m-d H:i:s', $time - 15);
        $endTime = date('Y-m-d H:i:s', $time + 15);

        // Busca todos os acessos que ocorreram dentro do intervalo de tempo e cujo 'Ratchet' está entre os valores especificados
        if ($gate == 'A') {
            $ratchets = 'Portaria A';
        }
        else if ($gate == 'B') $ratchets = 'Portaria B';
        else $ratchets = ['Portaria A', 'Portaria B'];

        // DB::select using raw SQL in the MultiClubes database

        $data = Self::queryAccess($startTime, $endTime, $ratchets);
        foreach ($data as $access) {
            $access->date =  explode(".", explode(" ", $access->AccessDateTime)[1])[0];
        }
        
        //Get the most probable access
        //Find the same plate in another day


        // Retorna a lista de pessoas que acessaram dentro do intervalo de tempo
        return $data;
    }



    public static function queryAccess($startTime, $endTime, $ratchet){
        return DB::connection('mc_sqlsrv')->select("SELECT RatchetAccesses.Date AS AccessDateTime,	Ratchets.Description AS Ratchet, AccessPlaces.Description AS AccessPlace, RatchetAccesses.Barcode AS AccessCode, TicketProducts.Description AS TicketProduct, EntranceTypes.Description AS EntranceType, COALESCE(VisitorsTitles.Code, Titles.Code, CarTitles.Code) AS TitleCode, TitleTypes.Name AS TitleType, COALESCE(Visitors.Name, Members.Name, CarMembers.Name,'Visitante') AS Name, COALESCE(Visitors.MobilePhone , Members.MobilePhone, CarMembers.MobilePhone,'Sem Telefone') AS Telephone  	FROM RatchetAccesses JOIN Ratchets ON RatchetAccesses.Ratchet = Ratchets.ID JOIN AccessPlaces ON AccessPlaces.Id = Ratchets.AccessPlace LEFT JOIN EntranceTypes ON EntranceTypes.Id = RatchetAccesses.EntranceType LEFT JOIN Authorizations ON RatchetAccesses.[Authorization] = Authorizations.ID LEFT JOIN Sales.Products AS TicketProducts ON TicketProducts.Id = Authorizations.Product AND TicketProducts.Type IN ( 	16,			17,			18  ) LEFT JOIN Visitors ON Visitors.ID = Authorizations.Visitor LEFT JOIN Titles AS VisitorsTitles ON VisitorsTitles.ID = Authorizations.Title LEFT JOIN Cars ON Cars.Id = RatchetAccesses.Car LEFT JOIN Titles AS CarTitles ON CarTitles.ID = Cars.Title LEFT JOIN (SELECT * FROM Members WHERE Members.Titular = 1) AS CarMembers ON CarMembers.Title = CarTitles.Id LEFT JOIN Members ON Members.ID = COALESCE(RatchetAccesses.Member, Authorizations.Member) LEFT JOIN Titles ON Members.Title = Titles.ID LEFT JOIN TitleTypes ON TitleTypes.ID = COALESCE(VisitorsTitles.TitleType, Titles.TitleType, CarTitles.TitleType) CROSS APPLY ( 	SELECT MAX(LastUpdateDate) AS LastUpdateDate 	FROM (VALUES 		(ISNULL(Ratchets.LastUpdateDate, ISNULL(Ratchets.CreationDate, '1900-01-01'))), 		(ISNULL(AccessPlaces.LastUpdateDate, ISNULL(AccessPlaces.CreationDate, '1900-01-01'))), 		(ISNULL(TicketProducts.LastUpdateDate, ISNULL(TicketProducts.CreationDate, '1900-01-01'))), 		(ISNULL(Titles.LastUpdateDate, ISNULL(Titles.CreationDate, '1900-01-01'))), 		(ISNULL(Members.LastUpdateDate, ISNULL(Members.CreationDate, '1900-01-01'))), 		(ISNULL(RatchetAccesses.Date, '1900-01-01')) 	) AS AllDates(LastUpdateDate) ) AS DependenciesLastUpdateCalculation CROSS APPLY ( 	SELECT MAX(LastUpdateDate) AS LastUpdateDate 	FROM (VALUES 		(ISNULL(RatchetAccesses.Date, '1900-01-01')) 	) AS AllDates(LastUpdateDate) ) AS LastUpdateCalculation 	WHERE RatchetAccesses.Mode = 0 AND  RatchetAccesses.Result = 1 AND   	 	RatchetAccesses.Date >= '" . $startTime . "' AND     	RatchetAccesses.Date <= '" . $endTime . "' AND  AccessPlaces.Description = '" . $ratchet . "' 	ORDER BY AccessDateTime DESC;");
    }
}
