<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class booking_data extends Controller
{
    public function index(Request $request)
    {
        $data = DB::table('booking_data')
            ->orderByRaw('
                CASE 
                    WHEN priority > 0 THEN priority
                    ELSE 0
                END DESC
            ')
            ->orderBy('star', 'desc')
            ->orderBy('review_count', 'desc')
            ->orderBy('score', 'desc')
            ->paginate(12);

                    
        foreach ($data as $item) {
            $rooms = DB::table('room_cache')
                ->where('booking_id', $item->id)
                ->get();
        
            $item->rooms = DB::table('room_cache')->where('booking_id', $item->id)->get();
        }


        $cities = DB::table('booking_data')
        ->select('city')
        ->distinct()
        ->pluck('city')
        ->toArray();

        $types = DB::table('booking_data')
        ->select('type')
        ->distinct()
        ->pluck('type')
        ->toArray();

        $facilities = DB::table('facilities')->get();


        return Inertia::render('BookingData', [
            'data' => $data,
            'cities' => $cities,
            'types' => $types,
            'facilities' => $facilities
        ]);
    }


    public function booking_page($booking_id)
    {
        $booking = DB::table('booking_data')->where('id', $booking_id)->get();

        // Получение id из booking_facilities для заданного booking_id
        $facilityIds = DB::table('booking_facilities')->where('booking_id', $booking_id)->pluck('facilities_id');
 
        // Получение названий удобств из facilities на основе полученных id
        $facilities = DB::table('facilities')->whereIn('id', $facilityIds)->pluck('title');
 

        return Inertia::render('SingleBookingData', [
            'booking' => $booking,
            'facilities' => $facilities
        ]);
    } 
    
    public function booking_data_rate(Request $request)
    {
        $rooms = NULL;

        if (isset($request->checkin) && isset($request->checkout)){
            $rooms = DB::table('rooms_2_day')
                ->where('booking_id', $request->booking_id)
                ->where('checkin', '>=', $request->checkin)
                ->where('checkin', '<=', $request->checkout)
                ->whereDate('checkin', '=', DB::raw('DATE(created_at)'))
                ->get();
        }
        else {
            $rooms = DB::table('rooms_2_day')
                ->where('booking_id', $request->booking_id)
                ->whereDate('checkin', '=', DB::raw('DATE(created_at)'))
                ->get();
        }


        $maxAvailableRooms = DB::table('rooms_30_day')
            ->select('room_type', DB::raw('MAX(max_available_rooms) AS max_available'))
            ->where('booking_id', $request->booking_id)
            ->groupBy('room_type')
        ->get();

        if (count($maxAvailableRooms) == 0) {
            $maxAvailableRooms = DB::table('rooms_2_day')
            ->select('room_type', DB::raw('MAX(available_rooms) AS max_available'))
            ->where('booking_id', $request->booking_id)
            ->groupBy('room_type')
            ->get();
        }



        $groupedRooms = $rooms->groupBy('room_type');

        $resultArray = [];

        foreach ($groupedRooms as $roomType => $group) {
            // Находим соответствующую запись в $maxAvailableRooms по room_type
            $maxAvailableRoom = $maxAvailableRooms->firstWhere('room_type', $roomType);

            // Если запись найдена, продолжаем вычисления
            if ($maxAvailableRoom) {
                // Сумма свободных комнат по типу
                $sum = $group->sum('available_rooms');

                // Количество записей по типу
                $count = $group->count();

                // Расчет занятости
                $occupancy = $sum / $count;  // среднее
                $occupancy = $maxAvailableRoom->max_available - $occupancy; // отнимает от максимального
                if ($occupancy > 0) $occupancy = round(($occupancy / $maxAvailableRoom->max_available) * 100, 2); // переводим в %
                if ($occupancy < 0) $occupancy = -1;
            } else {
                // Обработка ситуации, если не найдено соответствие
                $occupancy = -1;
            }

            // Добавляем результаты в массив
            $resultArray[] = [
                'room_type' => $roomType,
                'occupancy' => $occupancy,
            ];
        }

        return $resultArray;
    }

    public function booking_data_map(Request $request)
    {
        $data = $request->json()->all();

        $filterCity = !empty($data['city']) ? $data['city'] : [];
        $filterType = !empty($data['type']) ? $data['type'] : [];
        $filterFacilities = !empty($data['facilities']) ? $data['facilities'] : [];
        $filterPrice = !empty($data['price']) ? $data['price'] : [];

        $query = DB::table('booking_data');

        if (!empty($filterCity)) $query->whereIn('city', $filterCity);
        if (!empty($filterType)) $query->whereIn('type', $filterType);
        if (!empty($filterFacilities)) {
            foreach ($filterFacilities as $facility) {
                $query->whereExists(function ($subquery) use ($facility) {
                    $subquery->select(DB::raw(1))
                        ->from('booking_facilities')
                        ->whereRaw('booking_facilities.booking_id = booking_data.id')
                        ->where('facilities_id', $facility);
                });
            }
        }
        if (!empty($filterPrice)) {
            if (isset($filterPrice['min'])) $query->where('price', '>=', $filterPrice['min']);
            if (isset($filterPrice['max'])) $query->where('price', '<=', $filterPrice['max']);
        }


        $filteredData = $query->select('id', 'title', 'occupancy', 'price', 'location')->get();

        $coordinatesArray = [];

        foreach ($filteredData as $coord) {
            $coords = explode(',', $coord->location);

            if (count($coords) >= 2) {
                $coordinatesArray[] = [
                    'id' => $coord->id,
                    'title' => $coord->title,
                    'occupancy' => $coord->occupancy,
                    'price' => $coord->price,
                    'location' => [$coords[0], $coords[1]]
                ];
            }
        }

        if ($data) {
            return $coordinatesArray;
        }

        $cities = DB::table('booking_data')
            ->select('city')
            ->distinct()
            ->pluck('city')
            ->toArray();

        $types = DB::table('booking_data')
            ->select('type')
            ->distinct()
            ->pluck('type')
            ->toArray();

        $facilities = DB::table('facilities')->get();

        return Inertia::render('BookingDataMap', [
            'locations' => $coordinatesArray,
            'cities' => $cities,
            'types' => $types,
            'facilities' => $facilities
        ]);
    }


    public function booking_data_map_card ($booking_id)
    {
        $booking_data = DB::table('booking_data')
        ->select('id', 'title', 'description', 'star', 'images', 'location', 'type')
        ->where('id', $booking_id)
        ->get();

        $rooms = DB::table('room_cache')
                ->where('booking_id', $booking_id)
                ->get();


        // Получение id из booking_facilities для заданного booking_id
        $facilityIds = DB::table('booking_facilities')->where('booking_id', $booking_id)->pluck('facilities_id');
 
        // Получение названий удобств из facilities на основе полученных id
        $facilities = DB::table('facilities')->whereIn('id', $facilityIds)->pluck('title');
        

        $booking_data[0]->rooms = $rooms;
        $booking_data[0]->facilities = $facilities;


        return $booking_data[0];
    }

    public function booking_data_filters (Request $request)
    {
        $data = $request->json()->all();

        $filterTitle = $data['title'];
        $filterCity = $data['city'];
        $filterType = $data['type'];
        $filterFacilities = $data['facilities'];
        $filterPrice = $data['price'];
        $filterSort = $data['sort'];

        $query = DB::table('booking_data');

        if (empty($filterSort) || $filterSort == null) {
            $query ->orderBy('star', 'desc')->orderBy('review_count', 'desc')->orderBy('score', 'desc');
        }

        if (!empty($filterTitle)) {
            $query->where('title', 'like', '%' . $filterTitle . '%');
        }
        if (!empty($filterCity)) {
            $query->whereIn('city', $filterCity);
        }
        if (!empty($filterType)) {
            $query->whereIn('type', $filterType);
        }
        if (!empty($filterFacilities)) {
            foreach ($filterFacilities as $facility) {
                $query->whereExists(function ($subquery) use ($facility) {
                    $subquery->select(DB::raw(1))
                        ->from('booking_facilities')
                        ->whereRaw('booking_facilities.booking_id = booking_data.id')
                        ->where('facilities_id', $facility);
                });
            }
        }
        if (!empty($filterPrice)) {
            if (isset($filterPrice['min'])) $query->where('price', '>=', $filterPrice['min']);
            if (isset($filterPrice['max'])) $query->where('price', '<=', $filterPrice['max']);
        }
        if (!empty($filterSort)) {
            if ($filterSort == 'price') {
                $query->orderBy('price', 'desc');
            }
            elseif ($filterSort == 'rate') {
                $query->orderBy('score', 'desc');
            }
            elseif ($filterSort == 'occupancy') {
                $sortedBookingIds = DB::table('room_cache')
                    ->select('booking_id', DB::raw('AVG(occupancy_rate) as avg_occupancy'))
                    ->groupBy('booking_id')
                    ->orderByDesc('avg_occupancy')
                    ->pluck('booking_id');
                
                $sortedBookingIdsArray = $sortedBookingIds->toArray();

                $query
                ->whereIn('id', $sortedBookingIdsArray)
                ->orderBy(\DB::raw('FIELD(id, ' . implode(',', $sortedBookingIdsArray) . ')'));
            }
            elseif ($filterSort == 'room_type') {
                $countPerBookingId = DB::table('room_cache')
                    ->select('booking_id', DB::raw('COUNT(DISTINCT room_type) as room_type_count'))
                    ->groupBy('booking_id')
                    ->orderByDesc('room_type_count')
                    ->pluck('room_type_count', 'booking_id');

                $sortedBookingIdsArray = $countPerBookingId->keys()->toArray();

                $query
                    ->whereIn('id', $sortedBookingIdsArray)
                    ->orderBy(\DB::raw('FIELD(id, ' . implode(',', $sortedBookingIdsArray) . ')'))
                    ->paginate(12);
            }
            elseif ($filterSort == 'room_count') {
                $sumPerRoomType = DB::table('room_cache')
                    ->select('booking_id', DB::raw('SUM(max_available) as total_max_available'))
                    ->groupBy('booking_id')
                    ->orderByDesc('total_max_available')
                    ->pluck('total_max_available', 'booking_id');

                $sortedBookingIdsArray = $sumPerRoomType->keys()->toArray();

                $query
                    ->whereIn('id', $sortedBookingIdsArray)
                    ->orderBy(\DB::raw('FIELD(id, ' . implode(',', $sortedBookingIdsArray) . ')'))
                    ->paginate(12);
            }
        }
    
    
        $data = $query->orderBy('star', 'desc')->orderBy('review_count', 'desc')->paginate(12); 

        foreach ($data as $item) {
            $rooms = DB::table('room_cache')
                ->where('booking_id', $item->id)
                ->get();
        
            $item->rooms = DB::table('room_cache')->where('booking_id', $item->id)->get();
        }

        return $data;
    }

    public function setting_priority () 
    {
        $priority = DB::table('booking_data')
                    ->where('priority', '>', 0)
                    ->orderBy('priority', 'desc')
                    ->get();
                    // ->orderBy('review_count', 'desc')
                    // ->orderBy('score', 'desc');

        return Inertia::render('SettingPriorityPage', [
            'priority' => $priority
        ]);
    }

    public function priority_edit (Request $request)
    {
        switch ($request->msg) {
            case 'edit':
                DB::table('booking_data')->where('id', $request->id)->update(['priority' => $request->priority]);
              break;
            case 'delete':
                DB::table('booking_data')->where('id', $request->id)->update(['priority' => null]);
              break;
          }
          return DB::table('booking_data')
          ->where('priority', '>', 0)
          ->orderBy('priority', 'desc')
          ->get();
    }

    public function get_report(Request $request)
    {
        $data = DB::table('booking_data')
            ->whereIn('booking_data.id', $request->input('id'))
            ->leftJoin('room_cache', 'booking_data.id', '=', 'room_cache.booking_id')
            ->select([
                'booking_data.*',
                DB::raw('JSON_ARRAYAGG(JSON_OBJECT(
                    "room_type", room_cache.room_type,
                    "max_available", room_cache.max_available,
                    "occupancy_rate", room_cache.occupancy_rate
                )) AS rooms'),
            ])
            ->groupBy('booking_data.id')
            ->get();


        return Inertia::render('GetReport', [
            'data' => $data,
        ]);
    }

    public function get_all(Request $request)
    {
        $booking_id = $request->id;
        $booking_data = DB::table('booking_data')->where('id', $booking_id)->get();

        $facilityIds = DB::table('booking_facilities')->where('booking_id', $booking_id)->pluck('facilities_id');
        $facilities = DB::table('facilities')->whereIn('id', $facilityIds)->pluck('title');

        $rooms_2_day = DB::table('rooms_2_day')->where('booking_id', $booking_id)->get();
        $room_cache = DB::table('room_cache')->where('booking_id', $booking_id)->get();

        return [
            "booking_data" => $booking_data,
            "facilities" => $facilities,
            "rooms_2_day" => $rooms_2_day,
            "room_cache" => $room_cache
          ];
    }
}
