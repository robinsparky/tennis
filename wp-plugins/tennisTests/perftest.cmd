curl "http://tennisadmin.dev/tennisevent/ladies-singles/?manage=draw&bracket=Main" -w "Connect:%{time_connect}\nTotal:%{time_total}\nSpeed:%{speed_download}\nStatus:%{http_code}\nSize:%{size_download}\nURL:%{url_effective}\n" -o poopout -D poophead