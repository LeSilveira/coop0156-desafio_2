# coop0156-desafio_2
Desafio técnico Sicredi - Desenvolvedor PHP/Laravel
## Etapas implementadas
- CRUD de Clientes
- Integração com o Bureau e Regras de Negócio
- Tela de Simulação e Contratação
- Testes Automatizados
- Filas
- Algumas pequenas melhorias de UX (máscara de CPF, por exemplo)

## Abordagens
### Services
- Partindo da ideia de não incluir regras de negócio nos Controllers, foi uma boa oportunidade de utilizar Services para lidar com regras de negócio (AnaliseCreditoService) e chamadas externas (BureauCreditoService)

### Form Requests
- Almejando a separação bem definida entre as validações e os Controllers, foi cabível utilizar FormRequest para especificar as restrições dos campos de Cliente e AnaliseCredito quando feitas requisições que os utilizem. Também seria possível colocar essas validações escritas diretamente nos Controllers, mas achei que seria uma poluição desnecessária.

### Exception
- Foi criada uma classe para gerar um modelo de excessão específica de acordo com alguns erros que o bureau poderia gerar, talvez nem todos os casos tenham sido cobertos.

### Receber Cliente diretamente em rotas
- Em algumas funções em ClienteController (como show, por exemplo) foi utilizado o argumento do tipo Cliente, isso delega ao framework a busca por um registro existente de um cliente na base de dados, retornando 404 antes mesmo de cair no controller caso não encontre.