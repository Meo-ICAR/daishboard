<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'companies';

    /**
     * I campi assegnabili in massa (mass assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'urlogo',
        'url_attivazione',
        'email_admin',
        'db_secrete',
        'db_connection',
        'db_host',
        'db_port',
        'db_database',
        'database',
        'db_username',
        'db_password',
        'aibackground',
    ];

    /**
     * I campi da nascondere durante la serializzazione (es. nelle API).
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'db_password',
        'db_secrete',
    ];
}
