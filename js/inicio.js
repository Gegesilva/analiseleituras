document.addEventListener("DOMContentLoaded", function () {

    var table = document.getElementById("tabelaDados");
    if (!table) return;

    var headers = table.querySelectorAll("th.sortable");

    for (var i = 0; i < headers.length; i++) {
        (function (index) {

            headers[index].addEventListener("click", function () {

                var tbody = table.tBodies[0];
                var rows = Array.prototype.slice.call(tbody.rows, 0);

                var asc = this.classList.contains("asc");

                // remove classes de todos
                for (var j = 0; j < headers.length; j++) {
                    headers[j].classList.remove("asc", "desc");
                }

                this.classList.toggle("asc", !asc);
                this.classList.toggle("desc", asc);

                rows.sort(function (a, b) {

                    var A = a.cells[index].innerText.replace(/\s+/g, '').trim();
                    var B = b.cells[index].innerText.replace(/\s+/g, '').trim();

                    var numA = parseNumero(A);
                    var numB = parseNumero(B);

                    var isNumA = !isNaN(numA);
                    var isNumB = !isNaN(numB);

                    // Se ambos são números → ordena número
                    if (isNumA && isNumB) {
                        return asc ? numA - numB : numB - numA;
                    }

                    // fallback texto
                    return asc
                        ? A.localeCompare(B)
                        : B.localeCompare(A);
                });

                // reanexa linhas ordenadas
                for (var k = 0; k < rows.length; k++) {
                    tbody.appendChild(rows[k]);
                }

            });

        })(i);
    }

});

function parseNumero(valor) {
    if (!valor) return NaN;

    valor = valor.trim();

    // detecta formato
    if (valor.indexOf(',') > -1 && valor.indexOf('.') > -1) {

        // se vírgula vem depois → formato BR
        if (valor.lastIndexOf(',') > valor.lastIndexOf('.')) {
            valor = valor.replace(/\./g, '').replace(',', '.');
        } else {
            // formato US
            valor = valor.replace(/,/g, '');
        }

    } else if (valor.indexOf(',') > -1) {
        // só vírgula → BR
        valor = valor.replace(',', '.');
    }

    return parseFloat(valor);
}


/* conta as linhas */
document.addEventListener("DOMContentLoaded", function () {

    var tabela = document.getElementById("tabelaDados");
    if (!tabela) return;

    var linhas = tabela.tBodies[0].rows.length;

    document.getElementById("totalLinhas").innerText = linhas;

});