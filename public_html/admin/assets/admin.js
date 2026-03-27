document.addEventListener("DOMContentLoaded", () => {
  const flash = document.querySelector("[data-flash]");
  if (flash) {
    setTimeout(() => {
      flash.style.opacity = "0";
      flash.style.transform = "translateY(-4px)";
      flash.style.transition = "all .25s ease";
      setTimeout(() => flash.remove(), 260);
    }, 4500);
  }

  document.querySelectorAll("[data-confirm]").forEach((botao) => {
    botao.addEventListener("click", (evento) => {
      const mensagem = botao.getAttribute("data-confirm") || "Confirma esta ação?";
      if (!window.confirm(mensagem)) {
        evento.preventDefault();
      }
    });
  });

  const blocoAcompanhantes = document.getElementById("acompanhantes-dinamicos");
  const botaoAdicionar = document.getElementById("btn-add-acompanhante");
  const template = document.getElementById("template-acompanhante");

  if (blocoAcompanhantes && botaoAdicionar && template) {
    botaoAdicionar.addEventListener("click", () => {
      const clone = template.content.cloneNode(true);
      blocoAcompanhantes.appendChild(clone);
    });

    blocoAcompanhantes.addEventListener("click", (evento) => {
      const alvo = evento.target;
      if (!(alvo instanceof HTMLElement)) {
        return;
      }
      if (!alvo.matches("[data-remover-linha]")) {
        return;
      }
      const linha = alvo.closest(".acompanhante-linha");
      if (linha) {
        linha.remove();
      }
    });
  }

  const normalizarTexto = (texto) =>
    (texto || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();

  document.querySelectorAll("[data-select-filter]").forEach((campoFiltro) => {
    if (!(campoFiltro instanceof HTMLInputElement)) {
      return;
    }

    const selectId = campoFiltro.getAttribute("data-select-filter");
    if (!selectId) {
      return;
    }

    const selectAlvo = document.getElementById(selectId);
    if (!(selectAlvo instanceof HTMLSelectElement)) {
      return;
    }

    const aplicarFiltro = () => {
      const termo = normalizarTexto(campoFiltro.value.trim());

      Array.from(selectAlvo.options).forEach((opcao) => {
        const textoOpcao = normalizarTexto(opcao.textContent || "");
        const opcaoPadrao = opcao.value === "0";
        const visivel =
          termo === "" || textoOpcao.includes(termo) || opcao.selected || opcaoPadrao;

        opcao.hidden = !visivel;
      });

      if (!selectAlvo.multiple) {
        const selecionada = selectAlvo.selectedOptions[0];
        if (selecionada && selecionada.hidden) {
          selectAlvo.value = "0";
        }
      }
    };

    const sincronizarSelecaoPorTexto = () => {
      const termo = normalizarTexto(campoFiltro.value.trim());
      if (termo === "") {
        return;
      }

      const opcoes = Array.from(selectAlvo.options);
      const opcaoExata = opcoes.find((opcao) => {
        if (opcao.value === "0") {
          return false;
        }
        const textoOpcao = normalizarTexto(opcao.textContent || "");
        const textoApenasNome = normalizarTexto((opcao.textContent || "").split(" - ")[0]);
        return textoOpcao === termo || textoApenasNome === termo;
      });

      if (!opcaoExata) {
        return;
      }

      if (selectAlvo.multiple) {
        opcaoExata.selected = true;
      } else {
        selectAlvo.value = opcaoExata.value;
      }
    };

    campoFiltro.addEventListener("input", aplicarFiltro);
    campoFiltro.addEventListener("change", sincronizarSelecaoPorTexto);
    aplicarFiltro();
  });
});
