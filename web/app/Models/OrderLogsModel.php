<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OrderLogsModel extends Model{

    protected $table = 'order_logs';

    public function select_by_id($id){
        return DB::table($this->table)
            ->select('*')
            ->where('id',$id)
            ->get()->toArray();
    }
    public function select_by_shop($shop){
        return DB::table($this->table)
            ->select('*')
            ->where('shop',$shop)
            ->get()->toArray();
    }
    public function select_by_order_id($order_id){
        return DB::table($this->table)
            ->select('*')
            ->where('order_id',$order_id)
            ->where('log_type',"orders_create")
            ->get()->toArray();
    }
    public function select_by_refund_id($refund_id){
        return DB::table($this->table)
            ->select('*')
            ->where('refund_id',$refund_id)
            ->where('log_type',"refunds_create")
            ->get()->toArray();
    }
    public function select_raw_query($sql){
        return DB::select( $sql );
    }
    public function insert_order_logs($insertArr){
        return DB::table($this->table)
            ->insertGetId($insertArr);
    }
    public function update_order_logs($id,$updateArr){
        return DB::table($this->table)
            ->where('id',$id)
            ->update($updateArr);
    }
    public function delete_order_logs($id){
        return DB::table($this->table)
            ->where('id',$id)
            ->delete();
    }


}
