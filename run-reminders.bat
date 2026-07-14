@echo off
cd /d C:\Users\Kamil.Sabirov\PhpstormProjects\subscription-tracker
php bin/console app:notify-upcoming-payments >> var\log\reminders.log 2>&1
