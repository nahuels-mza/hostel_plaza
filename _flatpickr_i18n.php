<?php
/**
 * Nombres de días/meses del calendario (flatpickr) traducidos a mano.
 * Incluir justo DESPUÉS del <script> de flatpickr y ANTES de crear los calendarios.
 *
 * Por qué: Google Translate traducía las abreviaturas sueltas del calendario
 * ("Wed" → "Casarse", "Sat" → "Se sentó"). El calendario se marca "notranslate"
 * y su idioma (es, en, pt, fr, de) sigue al selector del sitio (localStorage hp_lang).
 */
?>
<script>
(function () {
    var LOCALES = {
        es: {
            weekdays: { shorthand: ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'],
                        longhand: ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'] },
            months:   { shorthand: ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'],
                        longhand: ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'] },
            firstDayOfWeek: 1
        },
        pt: {
            weekdays: { shorthand: ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'],
                        longhand: ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'] },
            months:   { shorthand: ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'],
                        longhand: ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'] },
            firstDayOfWeek: 0
        },
        fr: {
            weekdays: { shorthand: ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'],
                        longhand: ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'] },
            months:   { shorthand: ['Janv','Févr','Mars','Avr','Mai','Juin','Juil','Août','Sept','Oct','Nov','Déc'],
                        longhand: ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'] },
            firstDayOfWeek: 1
        },
        de: {
            weekdays: { shorthand: ['So','Mo','Di','Mi','Do','Fr','Sa'],
                        longhand: ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'] },
            months:   { shorthand: ['Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'],
                        longhand: ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'] },
            firstDayOfWeek: 1
        },
        en: {
            weekdays: { shorthand: ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'],
                        longhand: ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] },
            months:   { shorthand: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
                        longhand: ['January','February','March','April','May','June','July','August','September','October','November','December'] },
            firstDayOfWeek: 0
        }
    };
    function currentLang() {
        var l = '';
        try { l = localStorage.getItem('hp_lang') || ''; } catch (e) {}
        return LOCALES[l] ? l : (l ? 'en' : 'es');
    }
    function markNoTranslate(selectedDates, dateStr, fp) {
        fp.calendarContainer.classList.add('notranslate');
        fp.calendarContainer.setAttribute('translate', 'no');
    }
    function apply(lang) {
        var loc = LOCALES[lang] || LOCALES.en;
        flatpickr.setDefaults({ locale: loc, onReady: [markNoTranslate] });
        document.querySelectorAll('input').forEach(function (el) {
            if (el._flatpickr) el._flatpickr.set('locale', loc);
        });
    }
    apply(currentLang());
    // Si el huésped cambia de idioma sin recargar, se actualiza el calendario.
    var orig = window.changeLanguage;
    if (typeof orig === 'function') {
        window.changeLanguage = function (code) {
            var r = orig.apply(this, arguments);
            apply(LOCALES[code] ? code : 'en');
            return r;
        };
    }
})();
</script>
