document.addEventListener("DOMContentLoaded", function(event) {

    document.querySelector('#sincronizar_productos').onclick = function(ev) {
        ev.preventDefault();

        const data = new FormData();
        data.append( 'action', 'jugatoys_sincronizarProductos' );
        data.append( 'nonce', ajax_var.nonce );
        
        document.querySelector('#sincronizar_productos').innerHTML = 'Sincronizando...';
        document.querySelector('#sincronizar_productos').disabled = true;

        fetch(ajax_var.url, {
            method: 'POST',
            credentials: 'same-origin',
            body: data
        })
        .then(function(response) {
            document.querySelector('#sincronizar_productos').style.setProperty("color",'#ffffff', 'important');
            if(!response.ok) {
                throw Error(response.statusText);
            }
            return response.text();
        })
        .then(function(data) {
            document.querySelector('#sincronizar_productos').innerHTML = 'Sincronizado';
            document.querySelector('#sincronizar_productos').style.setProperty("background-color",'#00ff00', 'important');
        }
        ).catch(function(error) {
            document.querySelector('#sincronizar_productos').innerHTML = 'Error al sincronizar';
            document.querySelector('#sincronizar_productos').style.setProperty("background-color",'#ff0000', 'important');
        }
        ).finally(function() {            
        }
        );

        

    };

});