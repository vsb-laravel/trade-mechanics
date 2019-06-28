<?php
use Illuminate\Support\Facades\Route;

Route::resource('pairgroup','InstrumentGroupController');
Route::resource('instrument','InstrumentController');
Route::resource('deal','DealController');
// Candles
Route::get('/data/{type}/{arge?}', 'HistoController@histo')->name('histo')->where('type','histominute|histohour|histoday')->where('agre','[0-9]+');
Route::get('/sources','InstrumentController@sources');

// Route::get('/deal/add','DealController@store')->name('dealadd');
// Route::get('/deal/delete','DealController@destroy')->name('deal.delete');
// Route::post('/deal','DealController@store')->name('deal.store');
// Route::put('/deal/{deal}','DealController@update')->name('deal.update');
// Route::delete('/deal/{deal}','DealController@destroy')->name('deal.destroy');

// /* Deal controller JSON */
// Route::get('/{format}/deal','DealController@index')->name('deal.list')->where('format','json|html')->where('id','[0-9]+');
// Route::get('/{format}/deal/add','DealController@store')->name('deal.add')->where('format','json|html');
// Route::get('/{format}/deal/{id}/info','DealController@index')->name('deal.info')->where('format','json|html')->where('id','[0-9]+');
// Route::get('/{format}/deal/{id}/update','DealController@update')->name('deal.update')->where('format','json');
// Route::get('/{format}/deal/delete','DealController@destroy')->name('deal.delete')->where('format','json');
// Route::get('/{format}/deal/status','DealController@statuses')->name('deal.statuses')->where('format','json');
// /* Instruments */
// Route::get('/{format}/instrument/{id}','InstrumentController@index')->name('instrument.info')->where('format','json|html')->where('id','[0-9]+');
// Route::get('/{format}/instrument','InstrumentController@indexes')->name('instrument.list')->where('format','json|html');
// Route::get('/{format}/instrument/add','InstrumentController@store')->name('instrument.add')->where('format','json|html');
// Route::get('/{format}/instrument/{id}/update','InstrumentController@update')->name('instrument.update')->where('format','json|html')->where('id','[0-9]+');
// Route::get('/{format}/instrument/{id}/history','InstrumentController@history')->name('instrument.history')->where('format','json')->where('id','[0-9]+');
?>
