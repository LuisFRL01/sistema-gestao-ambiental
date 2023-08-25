<?php

namespace App\Http\Controllers;

use App\Models\Beneficiario;
use App\Http\Controllers\Controller;
use App\Models\SolicitacaoServico;
use App\Models\Endereco;
use App\Models\Telefone;
use Illuminate\Http\Request;
use App\Models\Pipeiro;
use PDF;

class SolicitacaoServicoController extends Controller
{

    public function index(Request $request)
    {
        $this->authorize('isSecretarioOrBeneficiario', User::class);

        $filtro = $request->input('filtro', 'default_value');

        $solicitacao_servicos = SolicitacaoServico::all();

        $motoristas = Pipeiro::all();

        switch ($filtro) {
            case 'andamento':
                $solicitacao_servicos = $solicitacao_servicos->where('status', 1);
                break;
            case 'finalizados':
                $solicitacao_servicos = $solicitacao_servicos->where('status', 3);
                break;
            case 'cancelados':
                $solicitacao_servicos = $solicitacao_servicos->where('status', 6);
                break;
        }

        $buscar = $request->input('buscar');

        if ($buscar != null) {
            $solicitacao_servicos = SolicitacaoServico::whereHas('beneficiario', function ($query) use ($buscar) {
                $query->where('nome', 'ILIKE', "%{$buscar}%")
                    ->orWhere('codigo', 'ILIKE', "%{$buscar}%")->orWhere('motorista', 'ILIKE', "%{$buscar}%");
            })->get();
        } else {
            $solicitacao_servicos = SolicitacaoServico::all();
        }

        $buscar = $request->input('buscar');

        if ($buscar != null) {
            $solicitacao_servicos = SolicitacaoServico::whereHas('beneficiario', function ($query) use ($buscar) {
                $query->where('nome', 'ILIKE', "%{$buscar}%")
                    ->orWhere('codigo', 'ILIKE', "%{$buscar}%")->orWhere('motorista', 'ILIKE', "%{$buscar}%");
            })->get();
        } else {
            $solicitacao_servicos = SolicitacaoServico::all();
        }

        return view('solicitacaoServicos.index', compact('solicitacao_servicos','motoristas'), ['filtro' => 'andamento']);
    }

    public function create()
    {
        $this->authorize('isSecretarioOrBeneficiario', User::class);

        $beneficiarios = Beneficiario::where('tipo_beneficiario', '=', Beneficiario::ROLE_ENUM['carro_pipa'])->orWhere('tipo_beneficiario', '=', Beneficiario::ROLE_ENUM['ambos'])->get();

        return view('solicitacaoServicos.create', compact('beneficiarios'));
    }

    public function store(Request $request)
    {
        $this->authorize('isSecretarioOrBeneficiario', User::class);

        $solicitacao_servico = new SolicitacaoServico();
        $solicitacao_servico->setAtributes($request);
        $solicitacao_servico->save();
        $motoristas = Pipeiro::all();

        return redirect()->route('solicitacao_servicos.index')->with('sucess', 'Solicitação de serviço cadastrada com sucesso!');
    }

    public function show($id)
    {
        $this->authorize('isSecretarioOrBeneficiario', User::class);
        $solicitacao_servico = SolicitacaoServico::find($id);
        $beneficiario = Beneficiario::find($solicitacao_servico->beneficiario_id);
        $endereco = Endereco::find($beneficiario->endereco_id);
        $telefone = Telefone::find($beneficiario->telefone_id);

        return view('solicitacaoServicos.show', compact('solicitacao_servico', 'beneficiario', 'endereco', 'telefone'));
    }

    public function edit($id)
    {
        $this->authorize('isSecretarioOrBeneficiario', User::class);

        $solicitacao_servico = SolicitacaoServico::find($id);
        $beneficiarios = Beneficiario::where('tipo_beneficiario', '=', Beneficiario::ROLE_ENUM['carro_pipa'])->orWhere('tipo_beneficiario', '=', Beneficiario::ROLE_ENUM['ambos'])->get();

        return view('solicitacaoServicos.edit', compact('solicitacao_servico', 'beneficiarios'));
    }

    public function update(Request $request, $id)
    {
        $this->authorize('isSecretarioOrBeneficiario', User::class);

        $solicitacao_servico = SolicitacaoServico::find($id);
        $solicitacao_servico->setAtributes($request);
        $solicitacao_servico->update();
        $motoristas = Pipeiro::all();

        return redirect()->route('solicitacao_servicos.index')->with('success', 'Solicitação de serviço atualizada com sucesso!');
    }

    public function AtualizarDataSaida(Request $request, $id)
    {
        $this->authorize('isSecretarioOrBeneficiario', User::class);

        $solicitacao_servico = SolicitacaoServico::find($id);
        $solicitacao_servico->data_saida = $request['data_saida'];
        $solicitacao_servico->status = 2;
        $solicitacao_servico->update();
        $motoristas = Pipeiro::all();

        return redirect()->route('solicitacao_servicos.index')->with('success', 'Data de saída atualizada com sucesso!');
    }

    public function AtualizarDataEntrega(Request $request, $id)
    {
        $this->authorize('isSecretarioOrBeneficiario', User::class);

        $solicitacao_servico = SolicitacaoServico::find($id);
        $solicitacao_servico->data_entrega = $request['data_entrega'];
        $solicitacao_servico->status = 3;
        $solicitacao_servico->update();


        return redirect()->route('solicitacao_servicos.index')->with('success', 'Data de entrega atualizada com sucesso!');
    }

    public function destroy($id)
    {
        $this->authorize('isSecretarioOrBeneficiario', User::class);

        $solicitacao_servico = SolicitacaoServico::find($id);
        $solicitacao_servico->delete();

        return redirect()->route('solicitacao_servicos.index')->with('success', 'Solicitação de serviço excluída com sucesso!');
    }


    public function gerarPedidosServicos(Request $request)
{
    $this->authorize('isSecretarioOrBeneficiario', User::class);
    $motoristaId = $request->input('motorista_id');
    $motorista = Pipeiro::find($motoristaId);
    if ($request->input('selected_items') == null) {
        return redirect()->route('solicitacao_servicos.index')
            ->with('error', 'Não há beneficiários válidos para gerar o PDF!');
    }
    $ids = $request->input('selected_items');
    $solicitacao_servicos = SolicitacaoServico::whereIn('id', $ids)->get();

    if ($solicitacao_servicos->isEmpty()) {
        return redirect()->route('solicitacao_servicos.index')
            ->with('error', 'Não há beneficiários válidos para gerar o PDF!');
    } else {
        $pdf = PDF::loadView('solicitacaoServicos.PDF.pedidos_carro_pipa', compact('solicitacao_servicos', 'motorista'));
        return $pdf->download('pedidos_servicos.pdf');
        
    }
}
}
