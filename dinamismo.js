let menu = document.getElementById('menu');
let contacto = document.getElementById('contacto');
let logo = document.getElementById('logo');
let lista = document.getElementById('lista');
let contenedorMenu2 = document.getElementById('contenedor-menu-2');


window.addEventListener('scroll', () => {

    if (window.scrollY > 865) {
        menu.classList.add("py-0", "bg-[#55301c]", "top-0");
        menu.classList.remove("bg-transparent", "top-8");

        contenedorMenu2.classList.add("pb-0");
        contenedorMenu2.classList.remove("pb-8");

        lista.classList.add("font-bold");
        lista.classList.remove("font-light");

        contacto.classList.remove("bg-[#F47E24]", "text-white");
        contacto.classList.add("bg-white", "text-[#55301c]", "hover:bg-[#F47E24]", "hover:text-white");

        logo.src = "./images/logo-blanco.svg";
        logo.classList.add("h-16");
        logo.classList.remove("h-36");
    }


    else if (window.scrollY > 60) {
        menu.classList.add("shadow-lg", "py-2", "top-0");
        menu.classList.remove("bg-transparent", "bg-[#55301c]", "top-8");


        lista.classList.add("font-light");
        lista.classList.remove("font-bold");


        logo.src = "./images/logo-blanco.svg";
        logo.classList.add("h-16");
        logo.classList.remove("h-36");
    }


    else {
        menu.classList.remove("shadow-lg", "bg-[#382212]", "py-2", "top-0");
        menu.classList.add("bg-transparent", "top-8");


        lista.classList.add("font-light");
        lista.classList.remove("font-bold");

        logo.src = "./images/logo-original-blanco.svg";
        logo.classList.remove("h-16");
        logo.classList.add("h-36");

        contacto.classList.remove("bg-white", "text-[#55301c]", "hover:bg-[#F47E24]", "hover:text-white");
        contacto.classList.add("bg-[#F47E24]", "text-white", "hover:bg-white", "hover:text-[#F47E24]");
    }
});

