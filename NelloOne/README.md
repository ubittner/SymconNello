## NelloOne
[![Version](https://img.shields.io/badge/Symcon_Version-5.1>-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Version](https://img.shields.io/badge/Modul_Version-2.01-blue.svg)
![Version](https://img.shields.io/badge/Modul_Build-2001-blue.svg)
![Version](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)

![Image](../imgs/logo-green.png)

Dieses Modul integriert [NelloOne](https://www.nello.io/de/nello-one), den sicheren Türöffner für dein Gegensprechtelefon, in [IP-Symcon](https://www.symcon.de).

Für dieses Modul besteht kein Anspruch auf Fehlerfreiheit, Weiterentwicklung, sonstige Unterstützung oder Support.

Bevor das Modul installiert wird, sollte unbedingt ein Backup von IP-Symcon durchgeführt werden.

Der Entwickler haftet nicht für eventuell auftretende Datenverluste oder sonstige Schäden.

Der Nutzer stimmt den o.a. Bedingungen, sowie den Lizenzbedingungen ausdrücklich zu.

### Inhaltverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)
8. [GUIDs](#8-guids)
9. [Changelog](#9-changelog)

### 1. Funktionsumfang

* Push-nachrichten, wenn jemand an der Tür klingelt
* Öffnen der Tür, bzw. Betätigen des Türsummers

### 2. Voraussetzungen

- IP-Symcon ab Version 5.1

### 3. Software-Installation

- Bei kommerzieller Nutzung (z.B. als Einrichter oder Integrator) wenden Sie sich bitte zunächst an den Autor.
  
- Bei privater Nutzung wird das Modul über den Modul Store installiert.

### 4. Einrichten der Instanzen in IP-Symcon

- In IP-Symcon an beliebiger Stelle `Instanz hinzufügen` auswählen und `Nello One` auswählen, welches unter dem Hersteller `Nello` aufgeführt ist. Es wird eine Nello One Instanz angelegt, in der die Eigenschaften zur Steuerung des Nello One gesetzt werden können.

__Konfigurationsseite__:

Name                                | Beschreibung
----------------------------------- | ---------------------------------
(0) Instanzinformationen            | Informationen zu der Instanz.
(1) Token                           | Token für die Authetifizierung am Nello Server.
(2) Standorte                       | Diese Liste beinhaltet die registrierten Nello Standorte.
(3) Webhook                         | Webhook für die Auslösenden Nello Ereignisse.
(4) Push Benachrichtigung           | Benachrichtigung bei ausgelöstem Ereighnis.

__Schaltflächen__:

Name                                | Beschreibung
----------------------------------- | ---------------------------------
(0) Instanzinformationen            |
Bedienungsanleitung                 | Zeigt Informationen zu diesem Modul an.
(1) Token                           | 
Registrierung                       | Holt den Token für die Authetifizierung am Nello Server ab.
(2) Standorte                       | 
Anzeigen                            | Zeigt die Standorte an.
Importieren                         | Importiert die Standorte vom Nello Server.
Tür öffnen                          | Öffnet die Tür, bzw. betätigt den Türsummer.
(3) Webhook                         | 
Hinzufügen/aktualisiere             | Fügt einen Webhook zum Nello Server hinzu oder aktualisert ihn.
Löschen                             | Löscht den Webhook für eingehende Nachrichten.

__Registrierung__:

Wählen Sie als erstes im Aktionsbereich unter Punkt (1) Token die Schaltfläche `REGISTRIEREN` aus, um sich bei Nello anzumelden.  
Loggen Sie sich mit Ihrem Nello Benutzerkonto ein.  
Bestätigen Sie Authentifizierung von IP-Symcon mit `Confirm`.  
Sofern der Vorgang erfolgreich war, erhalten Sie einen Bestätigungshinweis.  
Wählen Sie anschließend im Aktionsbereich unter Punkt (2) Standorte die Schaltfläche `IMPORTIEREN` aus, um die Standortdaten zu importieren.  
Unter Punkt (2) Standorte im oberen Konfigurationsbereich sollte(n) nun Ihr(e) Nello One Standort(e) aufgelistet sein.  

##### Hinweis:

Die Instanz kann immer nur einen Standort nutzen. Der ausgewählte, bzw. der Instanz zugewiesene Standort wird grün markiert.  
Sofern Sie mehr als einen Standort nutzen, führen Sie die Installation nochmals durch.  
Sie können dann aus der bereits vorhandenen Instanz den Token für die neue Instanz verwenden.  
Wählen Sie dann den Standort für die neue Instanz aus.  

Denken Sie daran, wenn Sie eine Instanz löschen und zuvor einen Webhook eingerichtet haben, diesen im Aktionsbereich unter Punkt (3) Webhook über die Schaltfläche `LÖSCHEN` zunächst zu löschen.

##### Webhooks:

Mit einem Webhook ist es möglich Informationen über Statusänderungen eines Nello Stamdortes zu erhalten,  
diese können wiederum mittels Push Benachrichtigung über das WebFront (vorhandene IP-Symcon Subskription vorausgesetzt) an die Benutzer weitergeleitet werden.
 
### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

##### Statusvariablen

Name                  | Typ       | Beschreibung
--------------------- | --------- | ----------------
LocationDescription   | String    | Beschreibung für den Standort
Door                  | Boolean   | Öffnet die Tür, bzw. betätigt den Türsummer

##### Profile:

Nachfolgende Profile werden zusätzlichen hinzugefügt:

Es werden keine neuen Profile angelegt.

### 6. WebFront

Über das WebFront kann der Türsummer betätigt werden.  

### 7. PHP-Befehlsreferenz

`NELLO_AddUpdateWebhook(integer $InstanzID)`

Fügt einen Webhook hinzu, um Meldungen über Statusänderungen des Standorts zu erhalten.

`NELLO_BuzzDoor(integer $InstanzID)`

Betätigt den Türsummer für den zugewiesenen Standort.

`NELLO_DeleteWebhook(integer $InstanzID)`

Löscht den für den Benutzer und Standort zugehörigen Webhook.

`NELLO_GetLocations(integer $InstanzID)`

Liefert die vorhandenen Standorte zurück.

`NELLO_ImportLocations(integer $InstanzID)`

Importiert die vorhandenen Standorte in die Instanz.

`NELLO_Register(integer $InstanzID)`

Führt die Registrierung für die Instanz durch, um den Token zu erhalten.

### 8. GUIDs

__Modul GUIDs__:

 Name       | GUID                                   | Bezeichnung  |
------------| -------------------------------------- | -------------|
Bibliothek  | {22BC7A02-3BFB-4DDD-9387-692E5771491D} | Library GUID |
Modul       | {0029BC2B-B0D2-4BC4-9D7E-08A9D8061F10} | Module GUID  |

### 9. Changelog

Version     | Datum      | Beschreibung
----------- | -----------| -------------------
2.01-2001   | 19.04.2019 | Modulanpassungen für den Module-Store
2.00        | 29.06.2018 | Modulumstellung auf API mit WebOAuth2 
